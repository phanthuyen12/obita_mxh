<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\User;

class ConversationPresenter
{
    /**
     * Compact conversation shape for list rows (Inbox + LiveChat).
     *
     * @return array<string, mixed>
     */
    public function conversationSummary(OmnichatConversation $conversation): array
    {
        $hasUnreadMessages = $conversation->messages()->where('direction', 'inbound')->whereNull('read_at')->exists();
        $unreadCount = max((int) data_get($conversation->meta, 'unread_count', 0), $hasUnreadMessages ? 1 : 0);

        $hasAiCare = $conversation->socialAccount
            ? filter_var(data_get($conversation->socialAccount->meta, 'ai_care.enabled', false), FILTER_VALIDATE_BOOLEAN)
            : filter_var(data_get($conversation->channel?->settings ?? [], 'ai_care.enabled', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'contact' => [
                'id' => $conversation->contact->id,
                'display_name' => $conversation->contact->display_name,
                'avatar_url' => $conversation->contact->avatar_url,
                'phone' => $conversation->contact->phone,
                'email' => $conversation->contact->email,
                'notes' => $conversation->contact->notes,
            ],
            'channel' => ['provider' => $conversation->socialAccount?->platform?->network() ?? $conversation->channel?->provider->value ?? 'website'],
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unreadCount,
            'ai_paused' => (bool) data_get($conversation->meta, 'ai_paused', false),
            'has_ai_care' => $hasAiCare,
            'status' => $conversation->status,
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
                'avatar_url' => $conversation->assignedUser->photo_url,
            ] : null,
            'labels' => $this->tagData($conversation),
        ];
    }

    /**
     * Full conversation shape for the opened conversation detail.
     *
     * @return array<string, mixed>
     */
    public function conversationData(OmnichatConversation $conversation): array
    {
        $hasUnreadMessages = $conversation->messages()->where('direction', 'inbound')->whereNull('read_at')->exists();
        $unreadCount = max((int) data_get($conversation->meta, 'unread_count', 0), $hasUnreadMessages ? 1 : 0);

        $hasAiCare = $conversation->socialAccount
            ? filter_var(data_get($conversation->socialAccount->meta, 'ai_care.enabled', false), FILTER_VALIDATE_BOOLEAN)
            : filter_var(data_get($conversation->channel?->settings ?? [], 'ai_care.enabled', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'channel_id' => $conversation->social_account_id ?? $conversation->channel_id,
            'contact_id' => $conversation->contact_id,
            'external_id' => $conversation->external_id,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'assigned_user_id' => $conversation->assigned_user_id,
            'subject' => null,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unreadCount,
            'ai_paused' => (bool) data_get($conversation->meta, 'ai_paused', false),
            'has_ai_care' => $hasAiCare,
            'contact' => [
                'id' => $conversation->contact->id,
                'workspace_id' => $conversation->contact->workspace_id,
                'display_name' => $conversation->contact->display_name,
                'first_name' => null,
                'last_name' => null,
                'avatar_url' => $conversation->contact->avatar_url,
                'email' => $conversation->contact->email,
                'phone' => $conversation->contact->phone,
                'notes' => $conversation->contact->notes,
                'locale' => $conversation->contact->locale,
                'timezone' => null,
                'status' => $conversation->contact->status,
                'is_lead' => $conversation->contact->is_lead,
                'lead_stage' => $conversation->contact->lead_stage,
                'phone_detected_at' => $conversation->contact->phone_detected_at?->toIso8601String(),
                'last_seen_at' => $conversation->contact->last_seen_at?->toIso8601String(),
                'created_at' => $conversation->contact->created_at->toIso8601String(),
                'updated_at' => $conversation->contact->updated_at->toIso8601String(),
                'total_conversations' => $conversation->contact->conversations()->count(),
                'last_contact_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'channel' => [
                'id' => $conversation->socialAccount?->id ?? $conversation->channel?->id,
                'workspace_id' => $conversation->workspace_id,
                'provider' => $conversation->socialAccount?->platform?->network() ?? $conversation->channel?->provider->value ?? 'website',
                'external_id' => $conversation->socialAccount?->platform_user_id ?? $conversation->channel?->external_id,
                'name' => $conversation->socialAccount?->display_label ?? $conversation->channel?->name ?? 'Website',
                'avatar_url' => $conversation->socialAccount?->avatar_url ?? $conversation->channel?->avatar_url,
                'status' => $conversation->socialAccount?->status->value ?? $conversation->channel?->status->value ?? 'disconnected',
                'is_active' => $conversation->socialAccount?->is_active ?? true,
                'capabilities' => ['messages'],
            ],
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
                'avatar_url' => $conversation->assignedUser->photo_url,
            ] : null,
            'labels' => $this->tagData($conversation),
            'created_at' => $conversation->created_at->toIso8601String(),
            'updated_at' => $conversation->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tagData(OmnichatConversation $conversation): array
    {
        return $conversation->tags->map(fn (OmnichatTag $tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function messageData(OmnichatMessage $message): array
    {
        $sender = $message->senderUser ?? $message->senderContact;

        return [
            'id' => $message->id,
            'workspace_id' => $message->workspace_id,
            'conversation_id' => $message->conversation_id,
            'sender_contact_id' => $message->sender_contact_id,
            'sender_user_id' => $message->sender_user_id,
            'external_id' => $message->external_id,
            'client_id' => $message->client_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $message->body,
            'status' => $message->status,
            'sender' => $sender !== null ? [
                'id' => $sender->id,
                'name' => $sender instanceof User ? $sender->name : $sender->display_name,
                'avatar_url' => $sender instanceof User ? $sender->photo_url : $sender->avatar_url,
            ] : null,
            'attachments' => $message->provider_payload['attachments'] ?? [],
            'metadata' => $message->provider_payload['metadata'] ?? [],
            'reply_to_message_id' => $message->provider_payload['reply_to_message_id'] ?? null,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'error_message' => $message->error_message,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
