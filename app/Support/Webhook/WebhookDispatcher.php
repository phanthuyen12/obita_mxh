<?php

declare(strict_types=1);

namespace App\Support\Webhook;

use App\Jobs\SendWorkspaceWebhookJob;
use App\Models\WorkspaceWebhook;
use App\Models\WorkspaceWebhookDelivery;

class WebhookDispatcher
{
    /**
     * Dispatch an event payload to all active webhooks subscribed to the event in the given workspace.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatch(string $workspaceId, string $event, array $payload): void
    {
        $webhooks = WorkspaceWebhook::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (! $webhook->hasEvent($event)) {
                continue;
            }

            /** @var WorkspaceWebhookDelivery $delivery */
            $delivery = WorkspaceWebhookDelivery::query()->create([
                'workspace_webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 1,
            ]);

            SendWorkspaceWebhookJob::dispatch($delivery);
        }
    }

    /**
     * Generate an HMAC-SHA256 signature for the webhook payload.
     */
    public static function signPayload(string $payloadJson, string $secret, int $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$payloadJson}", $secret);
    }
}
