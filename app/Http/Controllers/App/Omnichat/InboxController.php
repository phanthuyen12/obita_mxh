<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatChannel;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Omnichat\AccessibleChannelResolver;
use App\Support\Omnichat\ConversationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private readonly ConversationPresenter $presenter,
        private readonly AccessibleChannelResolver $channelResolver,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $this->authorize('view', $workspace);

        $resolved = $this->channelResolver->resolve($user, $workspace);
        $accounts = $resolved['accounts'];
        $customChannels = $resolved['customChannels'];
        $connectedChannels = $resolved['connectedChannels'];
        $selectedChannelIds = $resolved['selectedChannelIds'];
        $customChannelIds = $resolved['customChannelIds'];

        $availableChannelIds = $accounts->pluck('id');

        $focusedChannelId = in_array($user->current_omnichat_social_account_id, $availableChannelIds->all(), true)
            ? $user->current_omnichat_social_account_id
            : ($availableChannelIds->first() ?? $customChannelIds[0] ?? null);

        if ($user->current_omnichat_social_account_id !== $focusedChannelId && in_array($focusedChannelId, $availableChannelIds->all(), true)) {
            $user->update(['current_omnichat_social_account_id' => $focusedChannelId]);
        }

        $tab = $request->string('tab')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $channelIds = $selectedChannelIds;
        $tagId = $request->string('label')->trim()->toString();

        $conversationQuery = OmnichatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->with(['contact', 'socialAccount', 'channel', 'assignedUser', 'tags'])
            ->orderByRaw('COALESCE(last_message_at, updated_at) DESC');

        $conversationQuery->where(function ($query) use ($channelIds, $customChannelIds): void {
            $query->whereIn('social_account_id', $channelIds)
                ->orWhereIn('channel_id', $customChannelIds);
        });

        if ($search !== '') {
            $conversationQuery->whereHas('contact', fn ($query) => $query
                ->where('display_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($tab === 'unread') {
            $conversationQuery->where(function ($q) {
                $q->where('meta->unread_count', '>', 0)
                    ->orWhereHas('messages', fn ($m) => $m->where('direction', 'inbound')->whereNull('read_at'));
            });
        } elseif ($tab === 'mentions') {
            $conversationQuery->where('assigned_user_id', $user->id);
        }

        $conversationQuery->when($status !== '', fn ($query) => $query->where('status', $status));
        $conversationQuery->when($tagId !== '', fn ($query) => $query->whereHas(
            'tags',
            fn ($query) => $query->whereKey($tagId),
        ));

        $conversationModels = $conversationQuery->limit(100)->get();
        $conversations = [
            'data' => $conversationModels->map(fn (OmnichatConversation $conversation): array => $this->presenter->conversationSummary($conversation))->all(),
            'meta' => ['hasNextPage' => false],
        ];

        $conversationId = $request->has('conversation')
            ? $request->string('conversation')->toString()
            : $conversationModels->first()?->id;
        $selected = null;

        if (is_string($conversationId) && $conversationId !== '') {
            $selected = $conversationModels->firstWhere('id', $conversationId)
                ?? OmnichatConversation::query()
                    ->where('workspace_id', $workspace->id)
                    ->where(function ($query) use ($channelIds, $customChannelIds): void {
                        $query->whereIn('social_account_id', $channelIds)
                            ->orWhereIn('channel_id', $customChannelIds);
                    })
                    ->with(['contact', 'socialAccount', 'channel', 'assignedUser', 'tags'])
                    ->find($conversationId);
        }

        $messages = $selected !== null ? [
            'data' => $selected->messages()->with(['senderContact', 'senderUser'])->oldest('sent_at')->limit(200)->get()
                ->map(fn (OmnichatMessage $message): array => $this->presenter->messageData($message))->all(),
            'meta' => ['hasNextPage' => false],
        ] : null;

        // Calculate unread & mentions counts for the badges
        $totalUnreadCount = OmnichatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($channelIds, $customChannelIds): void {
                $query->whereIn('social_account_id', $channelIds)
                    ->orWhereIn('channel_id', $customChannelIds);
            })
            ->where(function ($q) {
                $q->where('meta->unread_count', '>', 0)
                    ->orWhereHas('messages', fn ($m) => $m->where('direction', 'inbound')->whereNull('read_at'));
            })
            ->count();

        $totalMentionsCount = OmnichatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($channelIds, $customChannelIds): void {
                $query->whereIn('social_account_id', $channelIds)
                    ->orWhereIn('channel_id', $customChannelIds);
            })
            ->where('assigned_user_id', $user->id)
            ->count();

        return Inertia::render('omnichat/Inbox', [
            'workspaceId' => $workspace->id,
            'selectedChannelIds' => $selectedChannelIds,
            'focusedChannelId' => $focusedChannelId,
            'conversations' => $conversations,
            'selectedConversation' => $selected !== null ? $this->presenter->conversationData($selected) : null,
            'messages' => $messages,
            'counts' => [
                'unread' => $totalUnreadCount,
                'mentions' => $totalMentionsCount,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status !== '' ? $status : null,
                'tab' => $tab !== '' ? $tab : 'all',
                'channel' => null,
                'assignee' => null,
                'label' => $tagId !== '' ? $tagId : null,
            ],
            'filterOptions' => [
                'channels' => array_map(fn (array $channel): array => [
                    'id' => $channel['id'], 'provider' => $channel['provider'], 'name' => $channel['name'],
                ], $connectedChannels),
                'assignees' => (function () use ($workspace, $user, $accounts, $customChannels): array {
                    $allMembers = $workspace->members()->orderBy('name')->get(['users.id', 'users.name', 'users.account_id', 'users.current_workspace_id']);

                    // User IDs that have access via social accounts (Facebook, Zalo, etc.)
                    $socialAccountUserIds = $accounts->flatMap(fn (SocialAccount $account): Collection => $account->sharedUsers->pluck('id'))
                        ->merge($accounts->filter(fn ($a) => $a->sharedUsers->isEmpty())->pluck('id')) // owner-accounts with no explicit sharing
                        ->unique();

                    // User IDs that have access via custom channels (Telegram, Website)
                    $customChannels->load('sharedUsers');
                    $channelUserIds = $customChannels->flatMap(fn (OmnichatChannel $ch): Collection => $ch->sharedUsers->pluck('id'))->unique();

                    // Admin/owner can always assign anyone in workspace
                    $canManage = $user->can('manageAccounts', $workspace);

                    return $allMembers
                        ->filter(function (User $member) use ($canManage, $channelUserIds, $accounts): bool {
                            if ($canManage) {
                                return true;
                            }
                            // Include if they have access via any social account
                            if ($accounts->isNotEmpty() && $accounts->contains(fn (SocialAccount $account): bool => $account->userHasAccess($member, 'can_view_omnichat'))) {
                                return true;
                            }
                            // Include if they have access via any custom channel (Telegram/Website)
                            if ($channelUserIds->contains($member->id)) {
                                return true;
                            }

                            return false;
                        })
                        ->map(fn (User $member): array => ['id' => $member->id, 'name' => $member->name, 'avatar_url' => $member->photo_url])
                        ->prepend(['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->photo_url])
                        ->unique('id')
                        ->values()
                        ->all();
                })(),
                'labels' => OmnichatTag::query()
                    ->where('workspace_id', $workspace->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'color']),
            ],
            'connectedChannels' => $connectedChannels,
            'permissions' => [
                'manageChannels' => $user->can('manageAccounts', $workspace),
                'assignConversations' => $user->can('assignConversations', $workspace),
                'sendMessages' => $user->can('viewOmnichat', $workspace),
                'editContacts' => true,
            ],
        ]);
    }
}
