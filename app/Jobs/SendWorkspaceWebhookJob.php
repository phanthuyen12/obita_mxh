<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkspaceWebhookDelivery;
use App\Support\Webhook\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SendWorkspaceWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(
        public WorkspaceWebhookDelivery $delivery,
    ) {}

    public function handle(): void
    {
        $delivery = $this->delivery->fresh(['webhook']);

        if (! $delivery || ! $delivery->webhook || ! $delivery->webhook->is_active) {
            return;
        }

        $webhook = $delivery->webhook;
        $jsonPayload = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $timestamp = time();
        $signature = WebhookDispatcher::signPayload($jsonPayload, $webhook->secret, $timestamp);

        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'KingHub-Webhook/1.0',
                    'X-KingHub-Event' => $delivery->event,
                    'X-KingHub-Delivery' => $delivery->id,
                    'X-KingHub-Timestamp' => (string) $timestamp,
                    'X-KingHub-Signature' => $signature,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $isSuccess = $response->successful();

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 2000),
                'duration_ms' => $durationMs,
                'status' => $isSuccess ? 'success' : 'failed',
                'error_message' => $isSuccess ? null : 'HTTP response status: '.$response->status(),
            ]);

            if (! $isSuccess && $response->serverError()) {
                // If 5xx server error, trigger job retry
                $this->release(30);
            }
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $delivery->update([
                'duration_ms' => $durationMs,
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(30);
            }
        }
    }
}
