<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\UpdateLeadRequest;
use App\Models\OmnichatContact;
use App\Models\OmnichatTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $search = $request->string('search')->trim()->toString();
        $stage = $request->string('stage')->trim()->toString();
        $provider = $request->string('provider')->trim()->toString();
        $tagId = $request->string('tag')->trim()->toString();

        $query = OmnichatContact::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_lead', true)
            ->whereNotNull('phone')
            ->with(['conversations' => fn ($query) => $query
                ->with(['socialAccount', 'tags'])
                ->latest('last_message_at')])
            ->latest('phone_detected_at');

        $query->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
            $query->where('display_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }));

        $query->when($stage !== '', fn ($query) => $query->where('lead_stage', $stage));
        $query->when($provider !== '', fn ($query) => $query->whereHas(
            'conversations.socialAccount',
            fn ($query) => $query->where('platform', $provider),
        ));
        $query->when($tagId !== '', fn ($query) => $query->whereHas(
            'conversations.tags',
            fn ($query) => $query->whereKey($tagId),
        ));

        $leads = $query->paginate(20)->withQueryString()->through(function (OmnichatContact $contact): array {
            $latestConversation = $contact->conversations->first();

            return [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'avatar_url' => $contact->avatar_url,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'lead_stage' => $contact->lead_stage,
                'phone_detected_at' => $contact->phone_detected_at?->toIso8601String(),
                'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
                'conversation_count' => $contact->conversations->count(),
                'latest_conversation_id' => $latestConversation?->id,
                'provider' => $latestConversation?->socialAccount?->platform?->network(),
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

        $providers = $workspace->socialAccounts()
            ->whereHas('omnichatConversations', fn ($query) => $query->whereHas(
                'contact',
                fn ($query) => $query->where('is_lead', true),
            ))
            ->get()
            ->map(fn ($account): array => [
                'value' => $account->platform->value,
                'label' => $account->platform->label(),
            ])
            ->unique('value')
            ->values();

        return Inertia::render('omnichat/Leads', [
            'leads' => $leads,
            'tags' => OmnichatTag::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name', 'color']),
            'providers' => $providers,
            'filters' => compact('search', 'stage', 'provider', 'tagId'),
        ]);
    }

    public function update(UpdateLeadRequest $request, OmnichatContact $contact): JsonResponse
    {
        $contact->update($request->validated());

        return response()->json(['lead' => $contact->only(['id', 'display_name', 'email', 'phone', 'notes', 'lead_stage'])]);
    }
}
