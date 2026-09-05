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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $this->authorize('view', $workspace);

        $accounts = $workspace->socialAccounts()
            ->omnichatAccessibleBy($user)
            ->with('sharedUsers')
            ->orderBy('display_name')
            ->get();
        $connectedChannels = $accounts->map(fn ($account): array => [
            'id' => $account->id,
            'provider' => $account->platform->network(),
            'name' => $account->display_label,
            'avatar_url' => $account->avatar_url,
            'status' => $account->status->value,
            'is_active' => $account->is_active,
        ])->values();

        $websiteChannels = OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'website')
            ->connected()
            ->when(
                ! $user->can('manageAccounts', $workspace),
                fn ($query) => $query->whereHas('sharedUsers', fn ($query) => $query
                    ->whereKey($user->id)
                    ->where('omnichat_channel_accesses.can_view_omnichat', true)),
            )
            ->orderBy('name')
            ->get();

        $connectedChannels = $connectedChannels->concat($websiteChannels->map(fn (OmnichatChannel $channel): array => [
            'id' => $channel->id,
            'provider' => 'website',
            'name' => $channel->name,
            'avatar_url' => null,
            'status' => $channel->status->value,
            'is_active' => true,
        ]))->values()->all();

        $availableChannelIds = $accounts->pluck('id');
        $savedChannelIds = $user->omnichatViewSocialAccounts()
            ->whereIn((new SocialAccount)->qualifyColumn('id'), $availableChannelIds)
            ->pluck((new SocialAccount)->qualifyColumn('id'))
            ->values()
            ->all();

        // Default to all active accounts if none saved, or ensure any active accounts are included
        if ($savedChannelIds === []) {
            $selectedChannelIds = $accounts->where('is_active', true)->pluck('id')->values()->all();
            if ($selectedChannelIds === []) {
                $selectedChannelIds = $availableChannelIds->values()->all();
            }
            $user->omnichatViewSocialAccounts()->sync($selectedChannelIds);
        } else {
            // Merge any active accounts so newly connected accounts are automatically visible
            $activeAccountIds = $accounts->where('is_active', true)->pluck('id')->values()->all();
            $selectedChannelIds = array_values(array_unique(array_merge($savedChannelIds, $activeAccountIds)));
            $user->omnichatViewSocialAccounts()->sync($selectedChannelIds);
        }

        $selectedChannelIds = array_values(array_unique(array_merge($selectedChannelIds, $websiteChannels->pluck('id')->all())));
        $websiteChannelIds = $websiteChannels->pluck('id')->all();

        $focusedChannelId = in_array($user->current_omnichat_social_account_id, $availableChannelIds->all(), true)
            ? $user->current_omnichat_social_account_id
            : ($availableChannelIds->first() ?? $websiteChannelIds[0] ?? null);

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

        $conversationQuery->where(function ($query) use ($channelIds, $websiteChannelIds): void {
            $query->whereIn('social_account_id', $channelIds)
                ->orWhereIn('channel_id', $websiteChannelIds);
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
            'data' => $conversationModels->map(fn (OmnichatConversation $conversation): array => $this->conversationSummary($conversation))->all(),
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
                    ->where(function ($query) use ($channelIds, $websiteChannelIds): void {
                        $query->whereIn('social_account_id', $channelIds)
                            ->orWhereIn('channel_id', $websiteChannelIds);
                    })
                    ->with(['contact', 'socialAccount', 'channel', 'assignedUser', 'tags'])
                    ->find($conversationId);
        }

        $messages = $selected !== null ? [
            'data' => $selected->messages()->with(['senderContact', 'senderUser'])->oldest('sent_at')->limit(200)->get()
                ->map(fn (OmnichatMessage $message): array => $this->messageData($message))->all(),
            'meta' => ['hasNextPage' => false],
        ] : null;

        // Calculate unread & mentions counts for the badges
        $totalUnreadCount = OmnichatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($channelIds, $websiteChannelIds): void {
                $query->whereIn('social_account_id', $channelIds)
                    ->orWhereIn('channel_id', $websiteChannelIds);
            })
            ->where(function ($q) {
                $q->where('meta->unread_count', '>', 0)
                    ->orWhereHas('messages', fn ($m) => $m->where('direction', 'inbound')->whereNull('read_at'));
            })
            ->count();

        $totalMentionsCount = OmnichatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($channelIds, $websiteChannelIds): void {
                $query->whereIn('social_account_id', $channelIds)
                    ->orWhereIn('channel_id', $websiteChannelIds);
            })
            ->where('assigned_user_id', $user->id)
            ->count();

        return Inertia::render('omnichat/Inbox', [
            'workspaceId' => $workspace->id,
            'selectedChannelIds' => $selectedChannelIds,
            'focusedChannelId' => $focusedChannelId,
            'conversations' => $conversations,
            'selectedConversation' => $selected !== null ? $this->conversationData($selected) : null,
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
                'assignees' => $workspace->members()->orderBy('name')->get(['users.id', 'users.name', 'users.account_id', 'users.current_workspace_id'])
                    ->filter(fn (User $member): bool => $accounts->contains(fn (SocialAccount $account): bool => $account->userHasAccess($member, 'can_view_omnichat')))
                    ->map(fn (User $member): array => ['id' => $member->id, 'name' => $member->name, 'avatar_url' => $member->photo_url])
                    ->prepend(['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->photo_url])
                    ->unique('id')->values()->all(),
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

    private function conversationSummary(OmnichatConversation $conversation): array
    {
        $hasUnreadMessages = $conversation->messages()->where('direction', 'inbound')->whereNull('read_at')->exists();
        $unreadCount = max((int) data_get($conversation->meta, 'unread_count', 0), $hasUnreadMessages ? 1 : 0);

        return [
            'id' => $conversation->id,
            'contact' => [
                'display_name' => $conversation->contact->display_name,
                'avatar_url' => $conversation->contact->avatar_url,
                'phone' => $conversation->contact->phone,
                'notes' => $conversation->contact->notes,
            ],
            'channel' => ['provider' => $conversation->socialAccount?->platform?->network() ?? $conversation->channel?->provider->value ?? 'website'],
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unreadCount,
            'status' => $conversation->status,
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
                'avatar_url' => $conversation->assignedUser->photo_url,
            ] : null,
            'labels' => $this->tagData($conversation),
        ];
    }

    private function conversationData(OmnichatConversation $conversation): array
    {
        $hasUnreadMessages = $conversation->messages()->where('direction', 'inbound')->whereNull('read_at')->exists();
        $unreadCount = max((int) data_get($conversation->meta, 'unread_count', 0), $hasUnreadMessages ? 1 : 0);

        return [
            'id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'channel_id' => $conversation->social_account_id ?? $conversation->channel_id,
            'contact_id' => $conversation->contact_id,
            'external_id' => $conversation->external_id,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'assigned_user_id' => $conversation->assigned_user_id,
            'subject' => null,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unreadCount,
            'contact' => [
                'id' => $conversation->contact->id,
                'workspace_id' => $conversation->contact->workspace_id,
                'display_name' => $conversation->contact->display_name,
                'first_name' => null,
                'last_name' => null,
                'avatar_url' => $conversation->contact->avatar_url,
                'email' => $conversation->contact->email,
                'phone' => $conversation->contact->phone,
                'notes' => $conversation->contact->notes,
                'locale' => $conversation->contact->locale,
                'timezone' => null,
                'status' => $conversation->contact->status,
                'is_lead' => $conversation->contact->is_lead,
                'lead_stage' => $conversation->contact->lead_stage,
                'phone_detected_at' => $conversation->contact->phone_detected_at?->toIso8601String(),
                'last_seen_at' => $conversation->contact->last_seen_at?->toIso8601String(),
                'created_at' => $conversation->contact->created_at->toIso8601String(),
                'updated_at' => $conversation->contact->updated_at->toIso8601String(),
                'total_conversations' => $conversation->contact->conversations()->count(),
                'last_contact_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'channel' => [
                'id' => $conversation->socialAccount?->id ?? $conversation->channel?->id,
                'workspace_id' => $conversation->workspace_id,
                'provider' => $conversation->socialAccount?->platform?->network() ?? $conversation->channel?->provider->value ?? 'website',
                'external_id' => $conversation->socialAccount?->platform_user_id ?? $conversation->channel?->external_id,
                'name' => $conversation->socialAccount?->display_label ?? $conversation->channel?->name ?? 'Website',
                'avatar_url' => $conversation->socialAccount?->avatar_url ?? $conversation->channel?->avatar_url,
                'status' => $conversation->socialAccount?->status->value ?? $conversation->channel?->status->value ?? 'disconnected',
                'is_active' => $conversation->socialAccount?->is_active ?? true,
                'capabilities' => ['messages'],
            ],
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
                'avatar_url' => $conversation->assignedUser->photo_url,
            ] : null,
            'labels' => $this->tagData($conversation),
            'created_at' => $conversation->created_at->toIso8601String(),
            'updated_at' => $conversation->updated_at->toIso8601String(),
        ];
    }

    private function tagData(OmnichatConversation $conversation): array
    {
        return $conversation->tags->map(fn (OmnichatTag $tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }

    private function messageData(OmnichatMessage $message): array
    {
        $sender = $message->senderUser ?? $message->senderContact;

        return [
            'id' => $message->id,
            'workspace_id' => $message->workspace_id,
            'conversation_id' => $message->conversation_id,
            'sender_contact_id' => $message->sender_contact_id,
            'sender_user_id' => $message->sender_user_id,
            'external_id' => $message->external_id,
            'client_id' => $message->client_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $message->body,
            'status' => $message->status,
            'sender' => $sender !== null ? [
                'id' => $sender->id,
                'name' => $sender instanceof User ? $sender->name : $sender->display_name,
                'avatar_url' => $sender instanceof User ? $sender->photo_url : $sender->avatar_url,
            ] : null,
            'attachments' => $message->provider_payload['attachments'] ?? [],
            'metadata' => $message->provider_payload['metadata'] ?? [],
            'reply_to_message_id' => $message->provider_payload['reply_to_message_id'] ?? null,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'error_message' => $message->error_message,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
