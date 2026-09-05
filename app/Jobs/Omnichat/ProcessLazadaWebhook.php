<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Support\Omnichat\PhoneNumberDetector;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessLazadaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public OmnichatWebhookEvent $webhookEvent) {}

    public function handle(PhoneNumberDetector $phoneNumberDetector): void
    {
        $data = data_get($this->webhookEvent->payload, 'data', []);
        $sessionId = (string) data_get($data, 'session_id');
        $isOutbound = (int) data_get($data, 'from_account_type') === 2;
        $customerId = (string) ($isOutbound ? data_get($data, 'to_account_id') : data_get($data, 'from_account_id', data_get($data, 'from_user_id')));

        if (! is_array($data) || $sessionId === '' || $customerId === '') {
            $this->webhookEvent->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        try {
            Cache::lock("omnichat:lazada:{$sessionId}", 30)->block(10, function () use ($data, $sessionId, $customerId, $isOutbound, $phoneNumberDetector): void {
                DB::transaction(function () use ($data, $sessionId, $customerId, $isOutbound, $phoneNumberDetector): void {
                    $event = OmnichatWebhookEvent::query()->lockForUpdate()->findOrFail($this->webhookEvent->id);
                    if ($event->status === 'processed') {
                        return;
                    }

                    $event->increment('attempts');
                    $event->update(['status' => 'processing', 'error_message' => null]);
                    $account = $event->socialAccount()->first();
                    if ($account === null) {
                        throw new RuntimeException('The Lazada shop connection no longer exists.');
                    }

                    $identity = OmnichatContactIdentity::query()->with('contact')
                        ->where('social_account_id', $account->id)->where('external_id', $customerId)->first();
                    if ($identity === null) {
                        $name = 'Lazada buyer · '.Str::of($customerId)->substr(-6);
                        $contact = OmnichatContact::query()->create([
                            'workspace_id' => $account->workspace_id, 'display_name' => $name,
                            'status' => 'active', 'last_seen_at' => $isOutbound ? null : now(), 'meta' => [],
                        ]);
                        $identity = $contact->identities()->create([
                            'workspace_id' => $account->workspace_id, 'social_account_id' => $account->id,
                            'provider' => 'lazada', 'external_id' => $customerId,
                            'display_name' => $name, 'meta' => ['site_id' => data_get($data, 'site_id')],
                        ]);
                    }

                    $contact = $identity->contact;
                    if ($contact === null) {
                        throw new RuntimeException('The Lazada contact identity is invalid.');
                    }

                    $conversation = OmnichatConversation::query()->firstOrCreate(
                        ['social_account_id' => $account->id, 'external_id' => $sessionId],
                        ['workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'status' => 'open', 'priority' => 'normal', 'meta' => []],
                    );

                    $content = json_decode((string) data_get($data, 'content', '{}'), true);
                    $content = is_array($content) ? $content : [];
                    $templateId = (int) data_get($data, 'template_id');
                    $body = data_get($content, 'txt', data_get($content, 'translateTxt'));
                    $body = is_string($body) ? $body : null;
                    $imageUrl = $templateId === 3 ? data_get($content, 'imgUrl') : null;
                    $attachments = is_string($imageUrl) && $imageUrl !== '' ? [[
                        'id' => hash('sha256', $imageUrl), 'type' => 'image', 'url' => $imageUrl,
                        'original_name' => '', 'mime_type' => 'image/jpeg', 'size' => 0,
                    ]] : [];
                    $sentAt = CarbonImmutable::createFromTimestampMs((int) data_get($data, 'send_time', now()->getTimestampMs()));
                    $message = OmnichatMessage::query()->firstOrCreate(
                        ['social_account_id' => $account->id, 'external_id' => (string) data_get($data, 'message_id')],
                        [
                            'workspace_id' => $account->workspace_id, 'conversation_id' => $conversation->id,
                            'sender_contact_id' => $isOutbound ? null : $contact->id,
                            'direction' => $isOutbound ? 'outbound' : 'inbound',
                            'type' => $attachments !== [] ? 'image' : ($body !== null ? 'text' : 'unsupported'),
                            'body' => $body, 'status' => $isOutbound ? 'sent' : 'delivered',
                            'provider_payload' => ['attachments' => $attachments, 'lazada' => $data], 'sent_at' => $sentAt,
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
                        'last_message_preview' => $body ?? '[image]', 'last_message_at' => $sentAt,
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
                'status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);
            throw $exception;
        }
    }
}
