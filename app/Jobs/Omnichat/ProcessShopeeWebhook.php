<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Actions\Omnichat\ImportShopeeConversation;
use App\Models\OmnichatWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ProcessShopeeWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public OmnichatWebhookEvent $webhookEvent) {}

    public function handle(ImportShopeeConversation $importer): void
    {
        try {
            Cache::lock('omnichat:shopee:'.$this->webhookEvent->external_event_id, 30)->block(10, function () use ($importer): void {
                $event = $this->webhookEvent->fresh();
                if ($event === null || $event->status === 'processed') {
                    return;
                }
                $event->increment('attempts');
                $event->update(['status' => 'processing', 'error_message' => null]);
                $account = $event->socialAccount;
                if ($account === null) {
                    $event->update(['status' => 'ignored', 'processed_at' => now()]);

                    return;
                }
                $payload = $event->payload;
                $data = is_array(data_get($payload, 'data')) ? data_get($payload, 'data') : $payload;
                $content = data_get($data, 'content');
                if (is_string($content)) {
                    $decoded = json_decode($content, true);
                    $content = is_array($decoded) ? $decoded : [];
                }
                $message = is_array($content) ? $content : $data;
                $type = (string) (data_get($message, 'type') ?: data_get($data, 'type'));
                if (in_array($type, ['mark_as_replied', 'mark_as_read'], true)) {
                    $event->update(['status' => 'ignored', 'processed_at' => now()]);

                    return;
                }
                $conversationData = [
                    'conversation_id' => data_get($message, 'conversation_id', data_get($data, 'conversation_id')),
                    'to_id' => (string) data_get($message, 'from_shop_id') === $account->platform_user_id ? data_get($message, 'to_id') : data_get($message, 'from_id', data_get($message, 'user_id')),
                    'to_name' => data_get($message, 'from_user_name', data_get($message, 'user_name')),
                    'to_avatar' => data_get($message, 'avatar_url', data_get($message, 'avatar')),
                    'business_type' => data_get($message, 'business_type', 0),
                ];
                $importer->conversation($account, $conversationData);
                $importer->message($account, $message, true);
                $event->update(['status' => 'processed', 'processed_at' => now(), 'error_message' => null]);
            });
        } catch (Throwable $exception) {
            $this->webhookEvent->newQuery()->whereKey($this->webhookEvent->id)->update([
                'status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);
            throw $exception;
        }
    }
}
