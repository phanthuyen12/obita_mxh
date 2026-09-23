<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\LiveChat\UpdateContactRequest;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatTag;
use App\Support\Omnichat\ConversationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function __construct(private readonly ConversationPresenter $presenter) {}

    /**
     * Paginated customer list for the mobile LiveChat "Khách hàng" tab.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $this->authorize('view', $workspace);

        $search = $request->string('search')->trim()->toString();
        $filter = $request->string('filter')->trim()->toString();
        $tagId = $request->string('tag')->trim()->toString();

        $query = OmnichatContact::query()
            ->where('workspace_id', $workspace->id)
            ->with(['conversations' => fn ($query) => $query
                ->with(['socialAccount', 'channel', 'tags'])
                ->latest('last_message_at')])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('updated_at');

        $query->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
            $query->where('display_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }));

        $query->when($tagId !== '', fn ($query) => $query->whereHas(
            'conversations.tags',
            fn ($query) => $query->whereKey($tagId),
        ));

        // Quick filters mirror the applivechat mock: phone presence, VIP, bought, potential.
        $query->when($filter === 'has_phone', fn ($query) => $query->whereNotNull('phone')->where('phone', '!=', ''));
        $query->when($filter === 'vip', fn ($query) => $query->whereHas(
            'conversations.tags',
            fn ($query) => $query->where('name', 'like', 'VIP%'),
        ));
        $query->when($filter === 'bought', fn ($query) => $query->where('lead_stage', 'converted'));
        $query->when($filter === 'potential', fn ($query) => $query->where('lead_stage', 'qualified'));

        $contacts = $query->paginate((int) config('app.pagination.default'))->withQueryString()->through(function (OmnichatContact $contact): array {
            $latestConversation = $contact->conversations->first();

            return [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'avatar_url' => $contact->avatar_url,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'notes' => $contact->notes,
                'lead_stage' => $contact->lead_stage,
                'is_lead' => $contact->is_lead,
                'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
                'phone_detected_at' => $contact->phone_detected_at?->toIso8601String(),
                'conversation_count' => $contact->conversations->count(),
                'latest_conversation_id' => $latestConversation?->id,
                'last_message_at' => $latestConversation?->last_message_at?->toIso8601String(),
                'last_message_preview' => $latestConversation?->last_message_preview,
                'provider' => $latestConversation?->socialAccount?->platform?->network()
                    ?? $latestConversation?->channel?->provider?->value,
                'tags' => $contact->conversations
                    ->flatMap->tags
                    ->unique('id')
                    ->map(fn (OmnichatTag $tag): array => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ])->values()->all(),
            ];
        });

        return response()->json([
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'has_next_page' => $contacts->hasMorePages(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Detailed customer profile for the LiveChat customer sheet:
     * contact info plus every conversation across channels.
     */
    public function show(Request $request, OmnichatContact $contact): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);
        abort_unless($contact->workspace_id === $workspace->id, 404);

        $contact->load([
            'conversations' => fn ($query) => $query
                ->with(['socialAccount', 'channel', 'tags', 'contact'])
                ->latest('last_message_at'),
        ]);

        $conversations = $contact->conversations
            ->map(fn (OmnichatConversation $conversation): array => $this->presenter->conversationSummary($conversation))
            ->values();

        return response()->json([
            'contact' => [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'avatar_url' => $contact->avatar_url,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'notes' => $contact->notes,
                'lead_stage' => $contact->lead_stage,
                'is_lead' => $contact->is_lead,
                'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
                'tags' => $contact->conversations
                    ->flatMap->tags
                    ->unique('id')
                    ->map(fn (OmnichatTag $tag): array => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ])->values()->all(),
            ],
            'conversations' => $conversations,
        ]);
    }

    /**
     * Update contact info and sync its customer-level tags.
     */
    public function update(UpdateContactRequest $request, OmnichatContact $contact): JsonResponse
    {
        $validated = $request->validated();

        $contact->update(collect($validated)->except('tag_ids')->all());

        if (array_key_exists('tag_ids', $validated)) {
            foreach ($contact->conversations as $conversation) {
                $conversation->tags()->sync($validated['tag_ids']);
            }
        }

        $tags = $contact->conversations()
            ->with('tags')
            ->get()
            ->flatMap->tags
            ->unique('id')
            ->map(fn (OmnichatTag $tag): array => $tag->only(['id', 'name', 'color']))
            ->values();

        return response()->json([
            'contact' => $contact->only(['id', 'display_name', 'email', 'phone', 'notes', 'lead_stage']),
            'tags' => $tags,
        ]);
    }

    /**
     * Create a tag directly from the mobile tag sheet.
     */
    public function storeTag(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $tag = OmnichatTag::query()->create([
            'workspace_id' => $workspace->id,
            ...$validated,
        ]);

        return response()->json(['tag' => $tag->only(['id', 'name', 'color'])], 201);
    }

    /**
     * Tag suggestions for the tag sheets.
     */
    public function tags(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $tags = OmnichatTag::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json(['data' => $tags]);
    }

    /**
     * Update tags of a single conversation (ChatRoom tag sheet).
     */
    public function updateConversationTags(Request $request, OmnichatContact $contact): JsonResponse
    {
        abort_unless($contact->workspace_id === $request->user()->current_workspace_id, 404);

        $validated = $request->validate([
            'conversation_id' => ['required', 'uuid', Rule::exists('omnichat_conversations', 'id')->where('workspace_id', $contact->workspace_id)],
            'tag_ids' => ['present', 'array', 'max:20'],
            'tag_ids.*' => [
                'uuid',
                Rule::exists('omnichat_tags', 'id')->where('workspace_id', $contact->workspace_id),
            ],
        ]);

        $conversation = $contact->conversations()->findOrFail($validated['conversation_id']);
        $conversation->tags()->sync($validated['tag_ids']);

        $tags = $conversation->tags()->orderBy('name')->get(['omnichat_tags.id', 'name', 'color']);

        return response()->json(['tags' => $tags]);
    }
}
