<?php

declare(strict_types=1);

namespace App\Actions\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\User;
use App\Support\Omnichat\FacebookMessengerClient;
use App\Support\Omnichat\LazadaClient;
use App\Support\Omnichat\ShopeeClient;
use App\Support\Omnichat\ZaloOaClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StoreMessage
{
    public function __construct(
        private readonly FacebookMessengerClient $facebookMessengerClient,
        private readonly ZaloOaClient $zaloOaClient,
        private readonly LazadaClient $lazadaClient,
        private readonly ShopeeClient $shopeeClient,
    ) {}

    public function execute(
        OmnichatConversation $conversation,
        User $sender,
        string $body,
        string $mode,
        string $clientId,
        ?UploadedFile $image = null,
    ): OmnichatMessage {
        $externalId = null;
        $providerPayload = [];

        if ($mode === 'internal' && $image !== null) {
            throw new RuntimeException('Internal notes do not support image attachments.');
        }

        if ($mode === 'reply') {
            if ($conversation->socialAccount?->platform?->value === 'facebook') {
                [$externalId, $providerPayload] = $this->sendFacebookMessage($conversation, $body, $image);
            } elseif ($conversation->socialAccount?->platform?->value === 'zalo-oa') {
                [$externalId, $providerPayload] = $this->sendZaloMessage($conversation, $body, $image);
            } elseif ($conversation->socialAccount?->platform?->value === 'lazada') {
                [$externalId, $providerPayload] = $this->sendLazadaMessage($conversation, $body, $image);
            } elseif ($conversation->socialAccount?->platform?->value === 'shopee') {
                [$externalId, $providerPayload] = $this->sendShopeeMessage($conversation, $body, $image);
            } elseif ($conversation->channel_id !== null && $image !== null) {
                $diskName = config('filesystems.default');
                $disk = Storage::disk($diskName);
                $path = $image->store('omnichat/website-chat', ['disk' => $diskName, 'visibility' => 'public']);
                $publicUrl = $disk->url($path);
                $providerPayload = [
                    'source' => 'website',
                    'attachments' => [[
                        'id' => (string) Str::uuid(),
                        'type' => $this->messageType($image),
                        'url' => $publicUrl,
                        'file_name' => $image->getClientOriginalName(),
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => (string) $image->getMimeType(),
                        'size' => (int) $image->getSize(),
                    ]],
                ];
            }
        }

        return DB::transaction(function () use ($conversation, $sender, $body, $mode, $clientId, $externalId, $providerPayload, $image): OmnichatMessage {
            $sentAt = now();
            $message = OmnichatMessage::query()->firstOrCreate(
                ['conversation_id' => $conversation->id, 'client_id' => $clientId],
                [
                    'workspace_id' => $conversation->workspace_id,
                    'social_account_id' => $conversation->social_account_id,
                    'channel_id' => $conversation->channel_id,
                    'sender_user_id' => $sender->id,
                    'external_id' => $externalId ?? (string) Str::uuid(),
                    'direction' => $mode === 'internal' ? 'internal' : 'outbound',
                    'type' => $this->messageType($image),
                    'body' => $body !== '' ? $body : null,
                    'status' => $mode === 'internal' || $externalId !== null || $conversation->channel_id !== null ? 'sent' : 'pending',
                    'provider_payload' => $providerPayload,
                    'sent_at' => $sentAt,
                ],
            );

            if ($message->wasRecentlyCreated) {
                $preview = $body !== '' ? $body : '['.$this->messageType($image).']';
                $conversation->update([
                    'last_message_preview' => $preview,
                    'last_message_at' => $sentAt,
                    'last_outbound_at' => $mode === 'reply' ? $sentAt : $conversation->last_outbound_at,
                ]);
                OmnichatMessageCreated::dispatch($message);
            }

            return $message;
        });
    }

    /** @return array{string, array<string, mixed>} */
    private function sendZaloMessage(OmnichatConversation $conversation, string $body, ?UploadedFile $image): array
    {
        $account = $conversation->socialAccount;
        if ($account === null || $conversation->external_id === null) {
            throw new RuntimeException('Zalo conversation is missing its OA connection or customer ID.');
        }

        if ($image === null) {
            $result = $this->zaloOaClient->sendText($account, $conversation->external_id, $body);

            return [$result['id'], ['zalo' => $result['payload']]];
        }

        $result = $this->zaloOaClient->sendAttachment($account, $conversation->external_id, $image, $body !== '' ? $body : null);
        $attachment = $result['attachment'];

        return [$result['id'], ['zalo' => $result['payload'], 'attachments' => [$attachment]]];
    }

    /** @return array{string, array<string, mixed>} */
    private function sendLazadaMessage(OmnichatConversation $conversation, string $body, ?UploadedFile $image): array
    {
        $account = $conversation->socialAccount;
        if ($account === null || $conversation->external_id === null) {
            throw new RuntimeException('Lazada conversation is missing its shop connection or session ID.');
        }

        if ($image === null) {
            $response = $this->lazadaClient->sendText($account, $conversation->external_id, $body);
            $messageId = (string) data_get($response, 'data.message_id', data_get($response, 'message_id'));

            return [$this->requireLazadaMessageId($messageId), ['lazada' => $response]];
        }

        if ($body !== '') {
            $this->lazadaClient->sendText($account, $conversation->external_id, $body);
        }

        [$width, $height] = getimagesize($image->getRealPath()) ?: [0, 0];
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $path = $image->store('omnichat/lazada', ['disk' => $diskName, 'visibility' => 'public']);
        $publicUrl = $disk->url($path);
        $response = $this->lazadaClient->sendImage($account, $conversation->external_id, $publicUrl, $width, $height);
        $messageId = (string) data_get($response, 'data.message_id', data_get($response, 'message_id'));

        return [$this->requireLazadaMessageId($messageId), [
            'lazada' => $response,
            'attachments' => [[
                'id' => hash('sha256', $publicUrl),
                'type' => 'image',
                'url' => $publicUrl,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => (string) $image->getMimeType(),
                'size' => (int) $image->getSize(),
            ]],
        ]];
    }

    /** @return array{string, array<string, mixed>} */
    private function sendShopeeMessage(OmnichatConversation $conversation, string $body, ?UploadedFile $image): array
    {
        if ($image !== null) {
            throw new RuntimeException('Shopee image upload is not supported by the source implementation.');
        }
        if (mb_strlen($body) > 600) {
            throw new RuntimeException('Shopee messages cannot exceed 600 characters.');
        }
        $account = $conversation->socialAccount;
        $recipientId = (string) data_get($conversation->meta, 'shopee_recipient_id');
        if ($account === null || $recipientId === '') {
            throw new RuntimeException('Shopee conversation is missing its shop connection or recipient ID.');
        }
        $response = $this->shopeeClient->sendText(
            $account, $recipientId, $body, $conversation->external_id,
            (int) data_get($conversation->meta, 'business_type', 0),
        );
        $messageId = (string) data_get($response, 'response.message_id');
        if ($messageId === '') {
            throw new RuntimeException('Shopee API returned no message ID.');
        }

        return [$messageId, ['shopee' => $response]];
    }

    private function requireLazadaMessageId(string $messageId): string
    {
        if ($messageId === '') {
            throw new RuntimeException('Lazada IM returned no message ID.');
        }

        return $messageId;
    }

    /** @return array{string, array<string, mixed>} */
    private function sendFacebookMessage(OmnichatConversation $conversation, string $body, ?UploadedFile $attachment): array
    {
        $account = $conversation->socialAccount;
        if ($account === null || $conversation->external_id === null) {
            throw new RuntimeException('Facebook conversation is missing its Page connection or customer ID.');
        }

        if ($attachment === null) {
            $result = $this->facebookMessengerClient->sendText($account, $conversation->external_id, $body);

            return [$result['id'], ['facebook' => $result['payload']]];
        }

        if ($body !== '') {
            $this->facebookMessengerClient->sendText($account, $conversation->external_id, $body);
        }

        $result = $this->facebookMessengerClient->sendAttachment($account, $conversation->external_id, $attachment);

        return [$result['id'], [
            'facebook' => $result['payload'],
            'attachments' => [$result['attachment']],
        ]];
    }

    private function messageType(?UploadedFile $attachment): string
    {
        if ($attachment === null) {
            return 'text';
        }

        return match (true) {
            str_starts_with((string) $attachment->getMimeType(), 'image/') => 'image',
            str_starts_with((string) $attachment->getMimeType(), 'video/') => 'video',
            str_starts_with((string) $attachment->getMimeType(), 'audio/') => 'audio',
            default => 'document',
        };
    }
}
