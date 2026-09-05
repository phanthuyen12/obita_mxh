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
use App\Support\Omnichat\FacebookMessengerClient;
use App\Support\Omnichat\PhoneNumberDetector;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessFacebookMessengerWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public OmnichatWebhookEvent $webhookEvent) {}

    public function handle(?PhoneNumberDetector $phoneNumberDetector = null, ?FacebookMessengerClient $facebookMessengerClient = null): void
    {
        $phoneNumberDetector ??= app(PhoneNumberDetector::class);
        $facebookMessengerClient ??= app(FacebookMessengerClient::class);
        $socialAccountId = $this->webhookEvent->social_account_id;
        $messagingEvent = data_get($this->webhookEvent->payload, 'messaging');
        $isEcho = data_get($messagingEvent, 'message.is_echo') === true;
        $customerId = $isEcho
            ? data_get($messagingEvent, 'recipient.id')
            : data_get($messagingEvent, 'sender.id');

        if (! is_string($socialAccountId) || ! is_array($messagingEvent) || ! is_string($customerId) || $customerId === '') {
            $this->webhookEvent->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'The Messenger event does not identify a customer.',
            ]);

            return;
        }

        $lock = Cache::lock("omnichat:facebook:{$socialAccountId}:{$customerId}", 30);

        try {
            $lock->block(10, function () use ($messagingEvent, $customerId, $isEcho, $phoneNumberDetector, $facebookMessengerClient): void {
                DB::transaction(function () use ($messagingEvent, $customerId, $isEcho, $phoneNumberDetector, $facebookMessengerClient): void {
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

                    $socialAccount = $webhookEvent->socialAccount()->first();

                    if ($socialAccount === null) {
                        throw new RuntimeException('The Facebook Page connection no longer exists.');
                    }

                    $profile = $this->fetchCustomerProfile($socialAccount, $customerId);
                    $identity = OmnichatContactIdentity::query()
                        ->with('contact')
                        ->where('social_account_id', $socialAccount->id)
                        ->where('external_id', $customerId)
                        ->first();

                    $profileName = $profile['name'] ?? 'Facebook user · '.Str::of($customerId)->substr(-6);
                    $profileAvatar = $profile['avatar_url'] ?? null;

                    if ($identity === null) {
                        $contact = OmnichatContact::query()->create([
                            'workspace_id' => $socialAccount->workspace_id,
                            'display_name' => $profileName,
                            'status' => 'active',
                            'avatar_url' => $profileAvatar,
                            'last_seen_at' => $isEcho ? null : now(),
                            'meta' => [],
                        ]);

                        $identity = $contact->identities()->create([
                            'workspace_id' => $socialAccount->workspace_id,
                            'social_account_id' => $socialAccount->id,
                            'provider' => 'facebook',
                            'external_id' => $customerId,
                            'display_name' => $profileName,
                            'avatar_url' => $profileAvatar,
                            'meta' => [],
                        ]);
                    }

                    $contact = $identity->contact;

                    if ($contact === null) {
                        throw new RuntimeException('The Facebook contact identity is invalid.');
                    }

                    if ($profileAvatar !== null || str_starts_with($contact->display_name, 'Facebook user ·')) {
                        $contact->update(array_filter([
                            'display_name' => $profileName,
                            'avatar_url' => $profileAvatar,
                        ], static fn (mixed $value): bool => $value !== null));
                    }

                    if ($profileAvatar !== null || str_starts_with($identity->display_name, 'Facebook user ·')) {
                        $identity->update(array_filter([
                            'display_name' => $profileName,
                            'avatar_url' => $profileAvatar,
                        ], static fn (mixed $value): bool => $value !== null));
                    }

                    $conversation = OmnichatConversation::query()->firstOrCreate(
                        [
                            'social_account_id' => $socialAccount->id,
                            'external_id' => $customerId,
                        ],
                        [
                            'workspace_id' => $socialAccount->workspace_id,
                            'contact_id' => $contact->id,
                            'status' => 'open',
                            'priority' => 'normal',
                            'meta' => [],
                        ],
                    );
                    $message = data_get($messagingEvent, 'message', []);
                    $messageId = data_get($message, 'mid');

                    if (! is_string($messageId) || $messageId === '') {
                        throw new RuntimeException('The Messenger event is missing its message ID.');
                    }

                    $body = data_get($message, 'text');
                    $body = is_string($body) ? $body : null;
                    $type = $this->messageType(is_array($message) ? $message : []);
                    $attachments = $this->attachments(is_array($message) ? $message : [], $facebookMessengerClient);
                    $sentAt = $this->sentAt($messagingEvent);
                    $direction = $isEcho ? 'outbound' : 'inbound';

                    $storedMessage = OmnichatMessage::query()->firstOrCreate(
                        [
                            'social_account_id' => $socialAccount->id,
                            'external_id' => $messageId,
                        ],
                        [
                            'workspace_id' => $socialAccount->workspace_id,
                            'conversation_id' => $conversation->id,
                            'sender_contact_id' => $isEcho ? null : $contact->id,
                            'direction' => $direction,
                            'type' => $type,
                            'body' => $body,
                            'status' => $isEcho ? 'sent' : 'delivered',
                            'provider_payload' => [
                                'attachments' => $attachments,
                                'reply_to' => data_get($message, 'reply_to'),
                                'quick_reply' => data_get($message, 'quick_reply'),
                            ],
                            'sent_at' => $sentAt,
                        ],
                    );

                    if ($storedMessage->wasRecentlyCreated) {
                        $phone = $isEcho ? null : $phoneNumberDetector->detect($body);

                        if ($phone !== null && $contact->phone === null) {
                            $contact->update(['phone' => $phone]);
                        }

                        OmnichatMessageCreated::dispatch($storedMessage);
                    }

                    $conversation->update([
                        'last_message_preview' => $body ?? "[{$type}]",
                        'last_message_at' => $sentAt,
                        $isEcho ? 'last_outbound_at' : 'last_inbound_at' => $sentAt,
                    ]);

                    if (! $isEcho) {
                        $contact->update(['last_seen_at' => $sentAt]);
                    }

                    $webhookEvent->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'error_message' => null,
                    ]);
                });
            });
        } catch (Throwable $exception) {
            $this->webhookEvent->newQuery()
                ->whereKey($this->webhookEvent->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 2000),
                ]);

            throw $exception;
        }
    }

    /** @return array{name: string|null, avatar_url: string|null} */
    private function fetchCustomerProfile(SocialAccount $socialAccount, string $customerId): array
    {
        $graphApi = rtrim((string) config('trypost.platforms.facebook.graph_api'), '/');

        try {
            $response = Http::timeout(5)->get($graphApi.'/'.rawurlencode($customerId), [
                'fields' => 'first_name,last_name,profile_pic',
                'access_token' => $socialAccount->access_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $name = trim(implode(' ', array_filter([
                    (string) data_get($data, 'first_name'),
                    (string) data_get($data, 'last_name'),
                ])));

                if ($name !== '' || is_string(data_get($data, 'profile_pic'))) {
                    return [
                        'name' => $name !== '' ? $name : null,
                        'avatar_url' => is_string(data_get($data, 'profile_pic')) ? data_get($data, 'profile_pic') : null,
                    ];
                }
            } else {
                Log::notice('Facebook Messenger customer profile unavailable', [
                    'status' => $response->status(),
                    'code' => data_get($response->json(), 'error.code'),
                    'subcode' => data_get($response->json(), 'error.error_subcode'),
                ]);
            }
        } catch (Throwable $exception) {
            Log::notice('Facebook Messenger customer profile request failed', ['message' => $exception->getMessage()]);
        }

        try {
            $response = Http::timeout(5)->get($graphApi.'/'.rawurlencode((string) $socialAccount->platform_user_id).'/conversations', [
                'fields' => 'participants',
                'user_id' => $customerId,
                'access_token' => $socialAccount->access_token,
            ]);

            if (! $response->successful()) {
                Log::notice('Facebook Messenger conversation participants unavailable', [
                    'status' => $response->status(),
                    'code' => data_get($response->json(), 'error.code'),
                    'subcode' => data_get($response->json(), 'error.error_subcode'),
                ]);

                return ['name' => null, 'avatar_url' => null];
            }

            $participants = data_get($response->json(), 'data.0.participants.data', []);
            $participant = collect(is_array($participants) ? $participants : [])->first(
                fn (mixed $participant): bool => is_array($participant)
                    && (string) data_get($participant, 'id') === $customerId,
            );
            $name = is_array($participant) && is_string(data_get($participant, 'name'))
                ? trim(data_get($participant, 'name'))
                : '';

            return ['name' => $name !== '' ? $name : null, 'avatar_url' => null];
        } catch (Throwable $exception) {
            Log::notice('Facebook Messenger conversation participants request failed', ['message' => $exception->getMessage()]);

            return ['name' => null, 'avatar_url' => null];
        }
    }

    /** @param array<string, mixed> $message */
    private function messageType(array $message): string
    {
        if (is_string(data_get($message, 'text'))) {
            return 'text';
        }

        $attachmentType = data_get($message, 'attachments.0.type');

        return match ($attachmentType) {
            'image', 'video', 'audio', 'file' => $attachmentType,
            default => 'unsupported',
        };
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}>
     */
    private function attachments(array $message, FacebookMessengerClient $facebookMessengerClient): array
    {
        $attachments = data_get($message, 'attachments', []);

        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->filter(fn (mixed $attachment): bool => is_array($attachment))
            ->map(fn (array $attachment): ?array => $facebookMessengerClient->downloadInboundAttachment($attachment))
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $messagingEvent */
    private function sentAt(array $messagingEvent): CarbonImmutable
    {
        $timestamp = data_get($messagingEvent, 'timestamp');

        return is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestampMs((int) $timestamp)
            : CarbonImmutable::now();
    }
}
