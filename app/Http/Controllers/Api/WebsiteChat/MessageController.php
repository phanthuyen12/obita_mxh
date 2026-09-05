<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebsiteChat;

use App\Events\OmnichatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WebsiteChat\StoreMessageRequest;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\OmnichatWebchatSession;
use App\Support\Omnichat\WebsiteChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function index(Request $request, WebsiteChatService $websiteChat): JsonResponse
    {
        $session = $this->session($request, $websiteChat);
        $after = $request->date('after');

        $messages = $session->conversation->messages()
            ->when($after, fn ($query) => $query->where('created_at', '>', $after))
            ->where('direction', '!=', 'internal')
            ->oldest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (OmnichatMessage $message): array => $this->messageData($message));

        $session->update(['last_seen_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function store(StoreMessageRequest $request, WebsiteChatService $websiteChat): JsonResponse
    {
        $session = $this->session($request, $websiteChat);
        $replyToMessageId = $request->validated('reply_to_message_id');
        $uploadedImage = $request->file('image');

        if ($replyToMessageId !== null && ! $session->conversation->messages()->whereKey($replyToMessageId)->exists()) {
            throw ValidationException::withMessages([
                'reply_to_message_id' => 'The selected reply to message does not belong to this conversation.',
            ]);
        }

        $message = DB::transaction(function () use ($request, $session, $uploadedImage): OmnichatMessage {
            $body = $request->string('body')->trim()->toString();
            $type = $request->string('type')->toString();
            $attachments = $request->validated('attachments', []);

            if ($uploadedImage !== null) {
                $diskName = config('filesystems.default');
                $disk = Storage::disk($diskName);
                $path = $uploadedImage->store('omnichat/website-chat', [
                    'disk' => $diskName,
                    'visibility' => 'public',
                ]);
                $attachments[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'image',
                    'url' => $disk->url($path),
                    'file_name' => $uploadedImage->getClientOriginalName(),
                    'mime_type' => (string) $uploadedImage->getMimeType(),
                    'size' => (int) $uploadedImage->getSize(),
                ];
            }
            $message = OmnichatMessage::query()->firstOrCreate(
                ['conversation_id' => $session->conversation_id, 'client_id' => $request->string('client_id')->toString()],
                [
                    'workspace_id' => $session->workspace_id,
                    'channel_id' => $session->channel_id,
                    'sender_contact_id' => $session->conversation->contact_id,
                    'external_id' => (string) Str::uuid(),
                    'direction' => 'inbound',
                    'type' => $type,
                    'body' => $body !== '' ? $body : null,
                    'status' => 'sent',
                    'provider_payload' => [
                        'source' => 'website',
                        'attachments' => $attachments,
                        'reply_to_message_id' => $request->validated('reply_to_message_id'),
                        'metadata' => $request->validated('metadata', []),
                    ],
                    'sent_at' => now(),
                ],
            );

            if ($message->wasRecentlyCreated) {
                $meta = $session->conversation->meta ?? [];
                $meta['unread_count'] = (int) data_get($meta, 'unread_count', 0) + 1;
                $session->conversation->update([
                    'last_message_preview' => $message->body ?? '['.$message->type.']',
                    'last_message_at' => $message->sent_at,
                    'last_inbound_at' => $message->sent_at,
                    'meta' => $meta,
                ]);

                // Auto-sync tags from message metadata
                $tagsToAttach = [];
                $ticketType = data_get($request->validated('metadata', []), 'ticket_type');
                if (is_string($ticketType) && $ticketType !== '') {
                    $tagsToAttach[] = $ticketType;
                }
                $rawMsgTags = data_get($request->validated('metadata', []), 'tags');
                if (is_string($rawMsgTags)) {
                    $tagsToAttach = array_merge($tagsToAttach, array_filter(array_map('trim', explode(',', $rawMsgTags))));
                } elseif (is_array($rawMsgTags)) {
                    $tagsToAttach = array_merge($tagsToAttach, array_filter(array_map('trim', $rawMsgTags)));
                }

                if (! empty($tagsToAttach)) {
                    $tagIds = [];
                    foreach (array_unique($tagsToAttach) as $tagName) {
                        $tag = OmnichatTag::query()->firstOrCreate(
                            ['workspace_id' => $session->workspace_id, 'name' => $tagName],
                            ['color' => '#E11D48'],
                        );
                        $tagIds[] = $tag->id;
                    }
                    $session->conversation->tags()->syncWithoutDetaching($tagIds);
                }

                $session->conversation->contact()->update(['last_seen_at' => now()]);
                $session->update(['last_seen_at' => now()]);
                OmnichatMessageCreated::dispatch($message);
            }

            return $message;
        });

        return response()->json(['message' => $this->messageData($message)], Response::HTTP_CREATED);
    }

    private function session(Request $request, WebsiteChatService $websiteChat): OmnichatWebchatSession
    {
        $session = $websiteChat->sessionFromRequest($request);
        abort_if($session === null || $session->origin !== $websiteChat->requestOrigin($request), 401);

        return $session;
    }

    /** @return array<string, mixed> */
    private function messageData(OmnichatMessage $message): array
    {
        return [
            'id' => $message->id,
            'client_id' => $message->client_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $message->body,
            'status' => $message->status,
            'attachments' => data_get($message->provider_payload, 'attachments', []),
            'reply_to_message_id' => data_get($message->provider_payload, 'reply_to_message_id'),
            'metadata' => data_get($message->provider_payload, 'metadata', []),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'error_message' => $message->error_message,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
