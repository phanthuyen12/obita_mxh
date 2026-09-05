<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use App\Support\Omnichat\PhoneNumberDetector;
use App\Support\Omnichat\ZaloOaClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessZaloOaWebhook implements ShouldQueue
{
    /** @var list<string> */
    private const USER_MESSAGE_EVENTS = [
        'user_send_text',
        'user_send_image',
        'user_send_link',
        'user_send_audio',
        'user_send_video',
        'user_send_sticker',
        'user_send_location',
        'user_send_business_card',
        'user_send_file',
    ];

    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public OmnichatWebhookEvent $webhookEvent) {}

    public function handle(PhoneNumberDetector $phoneNumberDetector, ZaloOaClient $zaloClient): void
    {
        $payload = $this->webhookEvent->payload;
        $eventName = (string) data_get($payload, 'event_name');

        if ((! str_starts_with($eventName, 'oa_send_') && ! in_array($eventName, self::USER_MESSAGE_EVENTS, true))) {
            $this->webhookEvent->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        $isOutbound = str_starts_with($eventName, 'oa_send_');
        $customerId = $isOutbound ? data_get($payload, 'recipient.id') : data_get($payload, 'sender.id');

        if (! is_scalar($customerId) || (string) $customerId === '') {
            $this->webhookEvent->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'The Zalo event does not identify a customer.',
            ]);

            return;
        }

        $customerId = (string) $customerId;
        $socialAccountId = (string) $this->webhookEvent->social_account_id;

        try {
            Cache::lock("omnichat:zalo-oa:{$socialAccountId}:{$customerId}", 30)->block(10, function () use ($payload, $eventName, $isOutbound, $customerId, $phoneNumberDetector, $zaloClient): void {
                $event = $this->webhookEvent->fresh(['socialAccount']);
                if ($event === null || $event->status === 'processed') {
                    return;
                }

                $account = $event->socialAccount;
                if ($account === null) {
                    throw new RuntimeException('The Zalo OA connection no longer exists.');
                }

                $profileData = [];
                $identityExists = OmnichatContactIdentity::query()
                    ->where('social_account_id', $account->id)
                    ->where('external_id', $customerId)
                    ->exists();
                Log::info('Zalo OA contact identity check', [
                    'social_account_id' => $account->id,
                    'user_id' => $customerId,
                    'identity_exists' => $identityExists,
                ]);
                if (! $identityExists) {
                    try {

                        $profileResponse = $zaloClient->userProfile((string) $account->access_token, $customerId);
                        $profileData = is_array(data_get($profileResponse, 'data'))
                            ? data_get($profileResponse, 'data')
                            : [];
                    } catch (Throwable $exception) {
                        Log::warning('Unable to load Zalo user profile', [
                            'social_account_id' => $account->id,
                            'user_id' => $customerId,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                DB::transaction(function () use ($payload, $eventName, $isOutbound, $customerId, $phoneNumberDetector, $profileData, $zaloClient): void {
                    $event = OmnichatWebhookEvent::query()->lockForUpdate()->findOrFail($this->webhookEvent->id);

                    if ($event->status === 'processed') {
                        return;
                    }

                    $event->increment('attempts');
                    $event->update(['status' => 'processing', 'error_message' => null]);
                    $account = $event->socialAccount()->first();

                    if ($account === null) {
                        throw new RuntimeException('The Zalo OA connection no longer exists.');
                    }

                    $identity = OmnichatContactIdentity::query()
                        ->with('contact')
                        ->where('social_account_id', $account->id)
                        ->where('external_id', $customerId)
                        ->first();

                    if ($identity === null) {
                        $displayName = trim((string) data_get($profileData, 'display_name'));
                        $displayName = $displayName !== ''
                            ? $displayName
                            : 'Zalo user · '.Str::of($customerId)->substr(-6);
                        $avatarUrl = data_get($profileData, 'avatar');
                        $avatarUrl = is_string($avatarUrl) && $avatarUrl !== '' ? $avatarUrl : null;
                        $contact = OmnichatContact::query()->create([
                            'workspace_id' => $account->workspace_id,
                            'display_name' => $displayName,
                            'avatar_url' => $avatarUrl,
                            'status' => 'active',
                            'last_seen_at' => $isOutbound ? null : now(),
                            'meta' => ['zalo_profile' => $profileData],
                        ]);
                        $identity = $contact->identities()->create([
                            'workspace_id' => $account->workspace_id,
                            'social_account_id' => $account->id,
                            'provider' => 'zalo-oa',
                            'external_id' => $customerId,
                            'display_name' => $displayName,
                            'avatar_url' => $avatarUrl,
                            'meta' => ['zalo_profile' => $profileData],
                        ]);
                    }

                    $contact = $identity->contact;
                    if ($contact === null) {
                        throw new RuntimeException('The Zalo contact identity is invalid.');
                    }

                    $conversation = OmnichatConversation::query()->firstOrCreate(
                        ['social_account_id' => $account->id, 'external_id' => $customerId],
                        [
                            'workspace_id' => $account->workspace_id,
                            'contact_id' => $contact->id,
                            'status' => 'open',
                            'priority' => 'normal',
                            'meta' => [],
                        ],
                    );

                    $messageId = (string) data_get($payload, 'message.msg_id');
                    if ($messageId === '') {
                        throw new RuntimeException('The Zalo event is missing its message ID.');
                    }

                    $body = $this->messageBody($payload, $eventName);
                    $attachments = $this->attachments($payload, $eventName, $account, $zaloClient);
                    $type = $this->messageType($attachments, $body);
                    $sentAt = $this->sentAt(data_get($payload, 'timestamp'));
                    $message = OmnichatMessage::query()->firstOrCreate(
                        ['social_account_id' => $account->id, 'external_id' => $messageId],
                        [
                            'workspace_id' => $account->workspace_id,
                            'conversation_id' => $conversation->id,
                            'sender_contact_id' => $isOutbound ? null : $contact->id,
                            'direction' => $isOutbound ? 'outbound' : 'inbound',
                            'type' => $type,
                            'body' => $body,
                            'status' => $isOutbound ? 'sent' : 'delivered',
                            'provider_payload' => ['attachments' => $attachments, 'event_name' => $eventName],
                            'sent_at' => $sentAt,
                        ],
                    );

                    if ($message->wasRecentlyCreated) {
                        $phone = $isOutbound ? null : $phoneNumberDetector->detect($body);
                        if ($phone !== null && $contact->phone === null) {
                            $contact->update(['phone' => $phone]);
                        }
                        OmnichatMessageCreated::dispatch($message);
                    }

                    $conversation->update([
                        'last_message_preview' => $body ?? $this->attachmentPreview($attachments),
                        'last_message_at' => $sentAt,
                        $isOutbound ? 'last_outbound_at' : 'last_inbound_at' => $sentAt,
                    ]);
                    if (! $isOutbound) {
                        $contact->update(['last_seen_at' => $sentAt]);
                    }
                    $event->update(['status' => 'processed', 'processed_at' => now(), 'error_message' => null]);
                });
            });
        } catch (Throwable $exception) {
            $this->webhookEvent->newQuery()->whereKey($this->webhookEvent->id)->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload
     * @return list<array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}>
     */
    private function attachments(array $payload, string $eventName, SocialAccount $account, ZaloOaClient $zaloClient): array
    {
        $message = is_array(data_get($payload, 'message')) ? data_get($payload, 'message') : [];
        $items = data_get($message, 'attachments', data_get($message, 'attachment', []));
        if ($items === [] && in_array($eventName, ['user_send_image', 'user_send_audio', 'user_send_video', 'user_send_file', 'user_send_sticker'], true)) {
            $items = [$message];
        }
        if (is_array($items) && array_key_exists('type', $items)) {
            $items = [$items];
        }
        if (! is_array($items)) {
            return [];
        }

        return collect($items)->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($account, $eventName, $zaloClient): ?array {
                $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
                $payload = array_replace($item, $payload);
                $element = is_array($payload['elements'][0] ?? null) ? $payload['elements'][0] : [];
                $payload = array_replace($payload, $element);
                $url = data_get($payload, 'url', data_get($payload, 'image_url', data_get($payload, 'file_url', data_get($payload, 'thumbnail'))));
                $type = $this->eventAttachmentType($eventName, $item, $payload);
                if ($type === 'template') {
                    $type = (string) data_get($payload, 'media_type', 'file');
                }
                $identifier = data_get($payload, 'id', data_get($payload, 'attachment_id', data_get($payload, 'token', data_get($payload, 'msg_id'))));

                if (! is_string($url) || $url === '') {
                    if (! is_string($identifier) || $identifier === '') {
                        return null;
                    }

                    $attachment = [
                        'id' => $identifier,
                        'type' => $type === 'media' ? (string) data_get($payload, 'media_type', 'image') : $type,
                        'url' => '',
                        'original_name' => (string) data_get($payload, 'name', ''),
                        'mime_type' => $type === 'image' ? 'image/jpeg' : 'application/octet-stream',
                        'size' => 0,
                    ];

                    Log::debug('Zalo OA attachment has no downloadable URL', [
                        'event_name' => $eventName,
                        'type' => $attachment['type'],
                        'identifier_present' => true,
                    ]);

                    return $attachment;
                }

                if ($url === '') {
                    return null;
                }

                try {
                    return $zaloClient->downloadInboundAttachment(
                        $account,
                        $url,
                        $type === 'media' ? (string) data_get($payload, 'media_type', 'image') : $type,
                        data_get($payload, 'name'),
                    );
                } catch (Throwable $exception) {
                    Log::warning('Unable to download Zalo OA inbound attachment', [
                        'social_account_id' => $account->id,
                        'url' => $url,
                        'message' => $exception->getMessage(),
                    ]);

                    return null;
                }

            })->filter()->values()->all();
    }

    private function messageBody(array $payload, string $eventName): ?string
    {
        $message = is_array(data_get($payload, 'message')) ? data_get($payload, 'message') : [];
        $text = data_get($message, 'text');
        if (is_string($text) && $text !== '') {
            return $text;
        }

        return match ($eventName) {
            'user_send_link' => $this->formatLinkBody($message),
            'user_send_location' => $this->formatLocationBody($message),
            'user_send_business_card' => $this->formatBusinessCardBody($message),
            default => null,
        };
    }

    private function eventAttachmentType(string $eventName, array $item, array $payload): string
    {
        return match ($eventName) {
            'user_send_image' => 'image',
            'user_send_audio' => 'audio',
            'user_send_video' => 'video',
            'user_send_file' => 'document',
            'user_send_sticker' => 'sticker',
            default => (string) data_get($item, 'type', data_get($payload, 'media_type', 'file')),
        };
    }

    private function formatLinkBody(array $message): ?string
    {
        $url = data_get($message, 'url', data_get($message, 'link_url'));
        $title = data_get($message, 'title');
        $description = data_get($message, 'description');
        $parts = array_filter([(is_string($title) ? $title : null), (is_string($description) ? $description : null), (is_string($url) ? $url : null)]);

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    private function formatLocationBody(array $message): ?string
    {
        $latitude = data_get($message, 'latitude', data_get($message, 'lat'));
        $longitude = data_get($message, 'longitude', data_get($message, 'lng'));
        $address = data_get($message, 'address', data_get($message, 'title'));
        $parts = array_filter([
            is_string($address) ? $address : null,
            is_numeric($latitude) && is_numeric($longitude) ? "{$latitude}, {$longitude}" : null,
        ]);

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    private function formatBusinessCardBody(array $message): ?string
    {
        $name = data_get($message, 'user_name', data_get($message, 'name'));
        $userId = data_get($message, 'user_id', data_get($message, 'id'));
        $parts = array_filter([
            is_string($name) ? $name : null,
            is_scalar($userId) && (string) $userId !== '' ? 'Zalo ID: '.(string) $userId : null,
        ]);

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    /** @param list<array<string, mixed>> $attachments */
    private function messageType(array $attachments, ?string $body): string
    {
        if ($attachments === []) {
            return $body !== null ? 'text' : 'unsupported';
        }

        return match ((string) ($attachments[0]['type'] ?? 'file')) {
            'image', 'media' => 'image',
            'sticker' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            default => 'document',
        };
    }

    /** @param list<array<string, mixed>> $attachments */
    private function attachmentPreview(array $attachments): string
    {
        return match ((string) ($attachments[0]['type'] ?? 'file')) {
            'image', 'media' => '[image]',
            'sticker' => '[sticker]',
            default => '[file]',
        };
    }

    private function sentAt(mixed $timestamp): CarbonImmutable
    {
        return is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestampMs((int) $timestamp)
            : CarbonImmutable::now();
    }
}
