<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebsiteChat;

use App\Events\OmnichatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WebsiteChat\StoreSessionRequest;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\OmnichatWebchatSession;
use App\Support\Omnichat\WebsiteChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function store(StoreSessionRequest $request, WebsiteChatService $websiteChat): JsonResponse
    {
        $channel = $websiteChat->channelFromRequest($request);
        $origin = $websiteChat->requestOrigin($request);
        abort_if($channel === null || ! $websiteChat->originIsAllowed($channel, $origin), 403);

        $plainToken = 'wc_session_'.Str::random(64);
        $visitorId = $request->string('visitor_id')->toString();

        [$session, $initialMessage] = DB::transaction(function () use ($request, $channel, $origin, $plainToken, $visitorId): array {
            $identity = OmnichatContactIdentity::query()
                ->where('channel_id', $channel->id)
                ->where('external_id', $visitorId)
                ->with('contact')
                ->first();

            if ($identity !== null) {
                $contact = $identity->contact;
                $contact->update([
                    'display_name' => $request->string('name')->trim()->toString() ?: $contact->display_name,
                    'email' => $request->string('email')->trim()->toString() ?: $contact->email,
                    'last_seen_at' => now(),
                ]);
            } else {
                $contact = OmnichatContact::query()->create([
                    'workspace_id' => $channel->workspace_id,
                    'display_name' => $request->string('name')->trim()->toString() ?: 'Khách website '.Str::upper(Str::substr($visitorId, 0, 6)),
                    'email' => $request->string('email')->trim()->toString() ?: null,
                    'locale' => $request->string('locale')->toString() ?: 'vi',
                    'meta' => ['identity_status' => 'unverified', 'source' => 'website'],
                    'last_seen_at' => now(),
                ]);

                OmnichatContactIdentity::query()->create([
                    'workspace_id' => $channel->workspace_id,
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                    'provider' => 'website',
                    'external_id' => $visitorId,
                    'display_name' => $contact->display_name,
                    'meta' => ['origin' => $origin],
                ]);
            }

            $conversation = OmnichatConversation::query()->create([
                'workspace_id' => $channel->workspace_id,
                'channel_id' => $channel->id,
                'contact_id' => $contact->id,
                'external_id' => (string) Str::uuid(),
                'status' => 'open',
                'priority' => 'normal',
                'last_message_preview' => $request->string('support_request')->trim()->toString(),
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'meta' => ['unread_count' => 1, 'origin' => $origin, 'context' => $request->validated('context', [])],
            ]);

            $initialMessage = OmnichatMessage::query()->create([
                'workspace_id' => $channel->workspace_id,
                'channel_id' => $channel->id,
                'conversation_id' => $conversation->id,
                'sender_contact_id' => $contact->id,
                'client_id' => (string) Str::uuid(),
                'external_id' => (string) Str::uuid(),
                'direction' => 'inbound',
                'type' => 'text',
                'body' => $request->string('support_request')->trim()->toString(),
                'status' => 'sent',
                'provider_payload' => ['source' => 'website', 'is_initial_request' => true],
                'sent_at' => now(),
            ]);

            $rawTags = data_get($request->validated('context', []), 'tags');
            if (is_string($rawTags)) {
                $tagNames = array_filter(array_map('trim', explode(',', $rawTags)));
            } elseif (is_array($rawTags)) {
                $tagNames = array_filter(array_map('trim', $rawTags));
            } else {
                $tagNames = [];
            }

            if (! empty($tagNames)) {
                $tagIds = [];
                foreach ($tagNames as $name) {
                    $tag = OmnichatTag::query()->firstOrCreate(
                        ['workspace_id' => $channel->workspace_id, 'name' => $name],
                        ['color' => '#E11D48'],
                    );
                    $tagIds[] = $tag->id;
                }
                $conversation->tags()->syncWithoutDetaching($tagIds);
            }

            $session = OmnichatWebchatSession::query()->create([
                'workspace_id' => $channel->workspace_id,
                'channel_id' => $channel->id,
                'conversation_id' => $conversation->id,
                'token_hash' => hash('sha256', $plainToken),
                'visitor_id_hash' => hash('sha256', $visitorId),
                'origin' => $origin,
                'locale' => $request->string('locale')->toString() ?: 'vi',
                'context' => $request->validated('context', []),
                'last_seen_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            return [$session, $initialMessage];
        });

        OmnichatMessageCreated::dispatch($initialMessage);

        return response()->json([
            'token' => $plainToken,
            'session_id' => $session->id,
            'conversation_id' => $session->conversation_id,
            'expires_at' => $session->expires_at->toIso8601String(),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, WebsiteChatService $websiteChat): JsonResponse
    {
        $session = $websiteChat->sessionFromRequest($request);
        abort_if($session === null || $session->origin !== $websiteChat->requestOrigin($request), 401);
        $session->update(['ended_at' => now()]);

        return response()->json(['ended' => true]);
    }
}
