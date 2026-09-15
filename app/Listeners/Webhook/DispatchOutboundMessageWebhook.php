<?php

declare(strict_types=1);

namespace App\Listeners\Webhook;

use App\Events\OmnichatMessageCreated;
use App\Support\Webhook\WebhookDispatcher;

class DispatchOutboundMessageWebhook
{
    public function handle(OmnichatMessageCreated $event): void
    {
        $message = $event->message;

        $eventName = $message->direction === 'inbound' ? 'message.inbound' : 'message.outbound';

        $payload = [
            'event' => $eventName,
            'message' => [
                'id' => $message->id,
                'workspace_id' => $message->workspace_id,
                'conversation_id' => $message->conversation_id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'status' => $message->status,
                'sender' => $message->senderUser !== null ? [
                    'type' => 'user',
                    'id' => $message->senderUser->id,
                    'name' => $message->senderUser->name,
                ] : ($message->senderContact !== null ? [
                    'type' => 'contact',
                    'id' => $message->senderContact->id,
                    'name' => $message->senderContact->display_name,
                ] : null),
                'sent_at' => $message->sent_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
        ];

        WebhookDispatcher::dispatch($message->workspace_id, $eventName, $payload);
        WebhookDispatcher::dispatch($message->workspace_id, 'message.created', $payload);
    }
}
