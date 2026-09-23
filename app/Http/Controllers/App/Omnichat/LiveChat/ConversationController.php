<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\LiveChat\IndexConversationRequest;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Support\Omnichat\AccessibleChannelResolver;
use App\Support\Omnichat\ConversationPresenter;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationPresenter $presenter,
        private readonly AccessibleChannelResolver $channelResolver,
    ) {}

    public function index(IndexConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $this->authorize('view', $workspace);

        $resolved = $this->channelResolver->resolve($user, $workspace);
        $channelIds = $resolved['selectedChannelIds'];
        $customChannelIds = $resolved['customChannelIds'];

        $tab = $request->string('tab')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
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
            $conversationQuery->where(function ($query) {
                $query->where('meta->unread_count', '>', 0)
                    ->orWhereHas('messages', fn ($messages) => $messages->where('direction', 'inbound')->whereNull('read_at'));
            });
        } elseif ($tab === 'mentions') {
            $conversationQuery->where('assigned_user_id', $user->id);
        }

        $conversationQuery->when($status !== '', fn ($query) => $query->where('status', $status));
        $conversationQuery->when($tagId !== '', fn ($query) => $query->whereHas(
            'tags',
            fn ($query) => $query->whereKey($tagId),
        ));

        $conversations = $conversationQuery->paginate((int) config('app.pagination.default'));

        return response()->json([
            'data' => $conversations->getCollection()
                ->map(fn (OmnichatConversation $conversation): array => $this->presenter->conversationSummary($conversation))
                ->all(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'has_next_page' => $conversations->hasMorePages(),
            ],
        ]);
    }

    public function show(OmnichatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['contact', 'socialAccount', 'channel', 'assignedUser', 'tags']);

        $messages = $conversation->messages()
            ->with(['senderContact', 'senderUser'])
            ->latest('sent_at')
            ->paginate((int) config('app.pagination.default'));

        return response()->json([
            'conversation' => $this->presenter->conversationData($conversation),
            'messages' => [
                'data' => $messages->getCollection()
                    ->reverse()
                    ->values()
                    ->map(fn (OmnichatMessage $message): array => $this->presenter->messageData($message))
                    ->all(),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'has_next_page' => $messages->hasMorePages(),
                ],
            ],
        ]);
    }
}
