<?php

declare(strict_types=1);

namespace App\Actions\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ImportShopeeConversation
{
    /** @param array<string, mixed> $data */
    public function conversation(SocialAccount $account, array $data): ?OmnichatConversation
    {
        $conversationId = (string) data_get($data, 'conversation_id');
        $recipientId = (string) data_get($data, 'to_id');
        if ($conversationId === '' || $recipientId === '') {
            return null;
        }

        return DB::transaction(function () use ($account, $data, $conversationId, $recipientId): OmnichatConversation {
            $name = (string) data_get($data, 'to_name', "Shopee user {$recipientId}");
            $identity = OmnichatContactIdentity::query()->with('contact')
                ->where('social_account_id', $account->id)->where('external_id', $recipientId)->first();
            if ($identity === null) {
                $contact = OmnichatContact::query()->create([
                    'workspace_id' => $account->workspace_id, 'display_name' => $name,
                    'avatar_url' => data_get($data, 'to_avatar'), 'status' => 'active', 'meta' => [],
                ]);
                $identity = $contact->identities()->create([
                    'workspace_id' => $account->workspace_id, 'social_account_id' => $account->id,
                    'provider' => 'shopee', 'external_id' => $recipientId, 'display_name' => $name,
                    'avatar_url' => data_get($data, 'to_avatar'), 'meta' => [],
                ]);
            }

            $latestContent = data_get($data, 'latest_message_content.text');
            $meta = [
                'unread_count' => (int) data_get($data, 'unread_count', 0),
                'shopee_recipient_id' => $recipientId,
                'business_type' => (int) data_get($data, 'business_type', 0),
                'pinned' => (bool) data_get($data, 'pinned', false),
                'muted' => (bool) data_get($data, 'mute', false),
            ];

            return OmnichatConversation::query()->updateOrCreate(
                ['social_account_id' => $account->id, 'external_id' => $conversationId],
                ['workspace_id' => $account->workspace_id, 'contact_id' => $identity->fresh()->contact_id,
                    'status' => 'open', 'priority' => 'normal', 'last_message_preview' => is_string($latestContent) ? $latestContent : null,
                    'meta' => $meta],
            );
        });
    }

    /** @param array<string, mixed> $data */
    public function message(SocialAccount $account, array $data, bool $incrementUnread = false): ?OmnichatMessage
    {
        $messageId = (string) data_get($data, 'message_id');
        $conversationId = (string) data_get($data, 'conversation_id');
        if ($messageId === '' || $conversationId === '') {
            return null;
        }

        $conversation = OmnichatConversation::query()->where('social_account_id', $account->id)
            ->where('external_id', $conversationId)->with('contact')->first();
        if ($conversation === null) {
            $details = ['conversation_id' => $conversationId, 'to_id' => data_get($data, 'from_id')];
            $conversation = $this->conversation($account, $details);
        }
        if ($conversation === null) {
            return null;
        }

        $content = data_get($data, 'content', []);
        $body = is_array($content) ? data_get($content, 'text') : null;
        $imageUrl = is_array($content) ? data_get($content, 'url', data_get($content, 'image_url')) : null;
        $body = is_string($body) && $body !== '' ? $body : (is_string($imageUrl) && $imageUrl !== '' ? '[Shopee image]' : '[Shopee '.data_get($data, 'message_type', 'message').']');
        $inbound = (string) data_get($data, 'from_shop_id') !== $account->platform_user_id;
        $sentAt = ((int) data_get($data, 'created_timestamp')) > 0
            ? CarbonImmutable::createFromTimestamp((int) data_get($data, 'created_timestamp')) : now();
        $attachments = is_string($imageUrl) && $imageUrl !== '' ? [[
            'id' => hash('sha256', $imageUrl), 'type' => 'image', 'url' => $imageUrl,
            'original_name' => '', 'mime_type' => 'image/jpeg', 'size' => 0,
        ]] : [];

        $message = OmnichatMessage::query()->firstOrCreate(
            ['social_account_id' => $account->id, 'external_id' => $messageId],
            ['workspace_id' => $account->workspace_id, 'conversation_id' => $conversation->id,
                'sender_contact_id' => $inbound ? $conversation->contact_id : null,
                'direction' => $inbound ? 'inbound' : 'outbound', 'type' => $attachments === [] ? 'text' : 'image',
                'body' => $body, 'status' => $inbound ? 'delivered' : 'delivered',
                'provider_payload' => ['attachments' => $attachments, 'shopee' => $data], 'sent_at' => $sentAt],
        );

        if ($message->wasRecentlyCreated) {
            $conversation->update([
                'last_message_preview' => $body, 'last_message_at' => $sentAt,
                $inbound ? 'last_inbound_at' : 'last_outbound_at' => $sentAt,
                'meta' => array_replace($conversation->meta ?? [], ['unread_count' => $incrementUnread && $inbound ? ((int) data_get($conversation->meta, 'unread_count', 0)) + 1 : (int) data_get($conversation->meta, 'unread_count', 0)]),
            ]);
            OmnichatMessageCreated::dispatch($message);
        }

        return $message;
    }
}
