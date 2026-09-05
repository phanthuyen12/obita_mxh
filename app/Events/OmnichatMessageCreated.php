<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OmnichatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OmnichatMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OmnichatMessage $message) {}

    public function broadcastAs(): string
    {
        return 'omnichat.message.created';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('omnichat.channel.'.($this->message->social_account_id ?? $this->message->channel_id));
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing(['senderContact', 'senderUser']);

        return ['message' => [
            'id' => $this->message->id,
            'workspace_id' => $this->message->workspace_id,
            'conversation_id' => $this->message->conversation_id,
            'sender_contact_id' => $this->message->sender_contact_id,
            'sender_user_id' => $this->message->sender_user_id,
            'external_id' => $this->message->external_id,
            'client_id' => $this->message->client_id,
            'direction' => $this->message->direction,
            'type' => $this->message->type,
            'body' => $this->message->body,
            'status' => $this->message->status,
            'sender' => $this->message->senderUser !== null ? [
                'id' => $this->message->senderUser->id,
                'name' => $this->message->senderUser->name,
                'avatar_url' => $this->message->senderUser->photo_url,
            ] : ($this->message->senderContact !== null ? [
                'id' => $this->message->senderContact->id,
                'name' => $this->message->senderContact->display_name,
                'avatar_url' => $this->message->senderContact->avatar_url,
            ] : null),
            'attachments' => $this->message->provider_payload['attachments'] ?? [],
            'metadata' => $this->message->provider_payload['metadata'] ?? [],
            'reply_to_message_id' => $this->message->provider_payload['reply_to_message_id'] ?? null,
            'sent_at' => $this->message->sent_at?->toIso8601String(),
            'delivered_at' => $this->message->delivered_at?->toIso8601String(),
            'read_at' => $this->message->read_at?->toIso8601String(),
            'failed_at' => $this->message->failed_at?->toIso8601String(),
            'error_message' => $this->message->error_message,
            'created_at' => $this->message->created_at->toIso8601String(),
        ]];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
