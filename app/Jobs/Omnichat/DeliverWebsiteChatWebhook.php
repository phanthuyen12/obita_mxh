<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Models\OmnichatChannel;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Services\Brand\SafeHttpFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverWebsiteChatWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120, 300];

    /** @param array<int, string> $tagIds */
    public function __construct(
        public string $event,
        public string $channelId,
        public string $resourceId,
        public array $tagIds = [],
    ) {}

    public function handle(SafeHttpFetcher $safeHttpFetcher): void
    {
        $channel = OmnichatChannel::query()->find($this->channelId);
        if ($channel === null || $channel->provider !== ChannelProvider::Website) {
            return;
        }

        $url = data_get($channel->settings, 'outbound_webhook.url');
        $events = data_get($channel->settings, 'outbound_webhook.events', []);
        if (! is_string($url) || ! in_array($this->event, $events, true) || $channel->webhook_secret === null) {
            return;
        }

        $payload = $this->payload($channel);
        if ($payload === null) {
            return;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $channel->webhook_secret);

        $safeHttpFetcher->guardedRequest($url, false)
            ->timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-KingHub-Event' => $this->event,
                'X-KingHub-Delivery' => $this->job?->uuid() ?? '',
                'X-KingHub-Signature' => 't='.$timestamp.',v1='.$signature,
            ])
            ->withBody($json, 'application/json')
            ->send('POST', $url)
            ->throw();
    }

    /** @return array<string, mixed>|null */
    private function payload(OmnichatChannel $channel): ?array
    {
        $data = match ($this->event) {
            'message.created' => $this->messageData(),
            'conversation.tagged' => $this->tagData(),
            default => null,
        };

        if ($data === null) {
            return null;
        }

        return [
            'id' => $this->job?->uuid(),
            'event' => $this->event,
            'created_at' => now()->toIso8601String(),
            'channel_id' => $channel->id,
            'data' => $data,
        ];
    }

    /** @return array<string, mixed>|null */
    private function messageData(): ?array
    {
        $message = OmnichatMessage::query()
            ->where('channel_id', $this->channelId)
            ->with('senderUser')
            ->find($this->resourceId);
        if ($message === null) {
            return null;
        }

        return [
            'conversation_id' => $message->conversation_id,
            'message' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'status' => $message->status,
                'sender' => $message->senderUser?->only(['id', 'name']),
                'sent_at' => $message->sent_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function tagData(): ?array
    {
        $conversation = OmnichatConversation::query()
            ->where('channel_id', $this->channelId)
            ->with('tags:id,name,color')
            ->find($this->resourceId);
        if ($conversation === null) {
            return null;
        }

        return [
            'conversation_id' => $conversation->id,
            'tags' => $conversation->tags->map->only(['id', 'name', 'color'])->values()->all(),
        ];
    }
}
