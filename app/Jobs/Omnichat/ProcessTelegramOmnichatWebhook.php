<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatChannel;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Support\Omnichat\PhoneNumberDetector;
use App\Support\Omnichat\TelegramOmnichatClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessTelegramOmnichatWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public OmnichatWebhookEvent $webhookEvent) {}

    public function handle(
        ?PhoneNumberDetector $phoneNumberDetector = null,
        ?TelegramOmnichatClient $telegramClient = null,
    ): void {
        $phoneNumberDetector ??= app(PhoneNumberDetector::class);
        $telegramClient ??= app(TelegramOmnichatClient::class);

        $channelId = data_get($this->webhookEvent->payload, 'channel_id');
        $message = data_get($this->webhookEvent->payload, 'message')
            ?? data_get($this->webhookEvent->payload, 'edited_message');

        // Check if there is a message in the update
        if (! is_string($channelId) || ! is_array($message)) {
            $this->webhookEvent->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'The Telegram update is not a message.',
            ]);

            return;
        }

        $from = data_get($message, 'from');
        $chat = data_get($message, 'chat');
        $customerId = (string) data_get($from, 'id');
        $chatId = (string) data_get($chat, 'id');

        // Ignore bot's own echo or invalid customer ID
        if ($customerId === '' || data_get($from, 'is_bot') === true) {
            $this->webhookEvent->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'The message is from a bot or missing sender.',
            ]);

            return;
        }

        $lock = Cache::lock("omnichat:telegram:{$channelId}:{$customerId}", 30);

        try {
            $lock->block(10, function () use ($channelId, $message, $from, $customerId, $chatId, $phoneNumberDetector, $telegramClient): void {
                DB::transaction(function () use ($channelId, $message, $from, $customerId, $chatId, $phoneNumberDetector, $telegramClient): void {
                    $webhookEvent = OmnichatWebhookEvent::query()
                        ->lockForUpdate()
                        ->findOrFail($this->webhookEvent->id);

                    if ($webhookEvent->status === 'processed') {
                        return;
                    }

                    $webhookEvent->increment('attempts');
                    $webhookEvent->update([
                        'status' => 'processing',
                        'error_message' => null,
                    ]);

                    /** @var OmnichatChannel|null $channel */
                    $channel = OmnichatChannel::query()->find($channelId);

                    if ($channel === null) {
                        throw new RuntimeException('The Telegram channel no longer exists.');
                    }

                    $firstName = (string) data_get($from, 'first_name', '');
                    $lastName = (string) data_get($from, 'last_name', '');
                    $username = data_get($from, 'username');
                    $displayName = trim("{$firstName} {$lastName}");

                    if ($displayName === '') {
                        $displayName = is_string($username) && $username !== ''
                            ? "@{$username}"
                            : "Telegram user · {$customerId}";
                    }

                    $identity = OmnichatContactIdentity::query()
                        ->with('contact')
                        ->where('channel_id', $channel->id)
                        ->where('external_id', $customerId)
                        ->first();

                    if ($identity === null) {
                        $avatarUrl = $telegramClient->fetchUserProfilePhoto($channel, $customerId);

                        $contact = OmnichatContact::query()->create([
                            'workspace_id' => $channel->workspace_id,
                            'display_name' => $displayName,
                            'status' => 'active',
                            'avatar_url' => $avatarUrl,
                            'last_seen_at' => now(),
                            'meta' => array_filter([
                                'telegram_username' => $username,
                                'telegram_user_id' => $customerId,
                            ]),
                        ]);

                        $identity = $contact->identities()->create([
                            'workspace_id' => $channel->workspace_id,
                            'channel_id' => $channel->id,
                            'provider' => 'telegram',
                            'external_id' => $customerId,
                            'display_name' => $displayName,
                            'avatar_url' => $avatarUrl,
                            'meta' => array_filter([
                                'username' => $username,
                            ]),
                        ]);
                    }

                    $contact = $identity->contact;

                    if ($contact === null) {
                        throw new RuntimeException('The Telegram contact identity is invalid.');
                    }

                    // Update display name if it was previously generic
                    if (str_starts_with($contact->display_name, 'Telegram user ·') && ! str_starts_with($displayName, 'Telegram user ·')) {
                        $contact->update(['display_name' => $displayName]);
                        $identity->update(['display_name' => $displayName]);
                    }

                    $conversation = OmnichatConversation::query()->firstOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'external_id' => $chatId,
                        ],
                        [
                            'workspace_id' => $channel->workspace_id,
                            'contact_id' => $contact->id,
                            'status' => 'open',
                            'priority' => 'normal',
                            'meta' => array_filter([
                                'telegram_chat_id' => $chatId,
                                'telegram_username' => $username,
                            ]),
                        ],
                    );

                    $messageId = (string) data_get($message, 'message_id');
                    if ($messageId === '') {
                        throw new RuntimeException('The Telegram update is missing its message_id.');
                    }

                    $body = data_get($message, 'text') ?? data_get($message, 'caption');
                    $body = is_string($body) ? $body : null;

                    [$type, $attachments] = $this->parseAttachments($channel, $message, $telegramClient);
                    $sentAt = $this->parseSentAt($message);

                    try {
                        $storedMessage = OmnichatMessage::query()->firstOrCreate(
                            [
                                'channel_id' => $channel->id,
                                'external_id' => $messageId,
                            ],
                            [
                                'workspace_id' => $channel->workspace_id,
                                'conversation_id' => $conversation->id,
                                'sender_contact_id' => $contact->id,
                                'direction' => 'inbound',
                                'type' => $type,
                                'body' => $body,
                                'status' => 'delivered',
                                'provider_payload' => [
                                    'telegram' => $message,
                                    'attachments' => $attachments,
                                ],
                                'sent_at' => $sentAt,
                            ],
                        );
                    } catch (UniqueConstraintViolationException) {
                        // Message already stored (duplicate webhook delivery) — fetch and skip
                        Log::info('[TelegramOmnichat] Duplicate message skipped (already exists)', [
                            'channel_id' => $channel->id,
                            'external_id' => $messageId,
                        ]);

                        return;
                    }

                    if ($storedMessage->wasRecentlyCreated) {
                        $phone = $phoneNumberDetector->detect($body ?? '');

                        // Also check if Telegram contact shared a contact card
                        if ($phone === null && is_string($cardPhone = data_get($message, 'contact.phone_number'))) {
                            $phone = $phoneNumberDetector->detect($cardPhone) ?? $cardPhone;
                        }

                        if ($phone !== null && $contact->phone === null) {
                            $contact->update([
                                'phone' => $phone,
                                'phone_detected_at' => now(),
                            ]);
                        }

                        OmnichatMessageCreated::dispatch($storedMessage);
                    }

                    $conversation->update([
                        'last_message_preview' => $body ?? "[{$type}]",
                        'last_message_at' => $sentAt,
                        'last_inbound_at' => $sentAt,
                    ]);

                    $contact->update(['last_seen_at' => $sentAt]);

                    $webhookEvent->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'error_message' => null,
                    ]);
                });
            });
        } catch (Throwable $exception) {
            Log::error('[TelegramOmnichat] Webhook processing failed', [
                'event_id' => $this->webhookEvent->id,
                'error' => $exception->getMessage(),
            ]);

            $this->webhookEvent->newQuery()
                ->whereKey($this->webhookEvent->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 2000),
                ]);

            throw $exception;
        }
    }

    /**
     * Parse message type and download attachments.
     *
     * @param  array<string, mixed>  $message
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function parseAttachments(OmnichatChannel $channel, array $message, TelegramOmnichatClient $client): array
    {
        // 1. Check Photos
        $photos = data_get($message, 'photo');
        if (is_array($photos) && ! empty($photos)) {
            $biggestPhoto = end($photos);
            $fileId = data_get($biggestPhoto, 'file_id');
            if (is_string($fileId)) {
                $downloaded = $client->downloadInboundFile($channel, $fileId, 'image', 'photo.jpg');
                if ($downloaded !== null) {
                    return ['image', [$downloaded]];
                }
            }

            return ['image', []];
        }

        // 2. Check Document
        $document = data_get($message, 'document');
        if (is_array($document)) {
            $fileId = data_get($document, 'file_id');
            $fileName = (string) data_get($document, 'file_name', 'document');
            if (is_string($fileId)) {
                $downloaded = $client->downloadInboundFile($channel, $fileId, 'document', $fileName);
                if ($downloaded !== null) {
                    return ['document', [$downloaded]];
                }
            }

            return ['document', []];
        }

        // 3. Check Voice / Audio
        $voice = data_get($message, 'voice') ?? data_get($message, 'audio');
        if (is_array($voice)) {
            $fileId = data_get($voice, 'file_id');
            if (is_string($fileId)) {
                $downloaded = $client->downloadInboundFile($channel, $fileId, 'audio', 'voice.ogg');
                if ($downloaded !== null) {
                    return ['audio', [$downloaded]];
                }
            }

            return ['audio', []];
        }

        // 4. Check Video
        $video = data_get($message, 'video');
        if (is_array($video)) {
            $fileId = data_get($video, 'file_id');
            if (is_string($fileId)) {
                $downloaded = $client->downloadInboundFile($channel, $fileId, 'video', 'video.mp4');
                if ($downloaded !== null) {
                    return ['video', [$downloaded]];
                }
            }

            return ['video', []];
        }

        return ['text', []];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function parseSentAt(array $message): CarbonImmutable
    {
        $timestamp = data_get($message, 'date');

        return is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestamp((int) $timestamp)
            : CarbonImmutable::now();
    }
}
