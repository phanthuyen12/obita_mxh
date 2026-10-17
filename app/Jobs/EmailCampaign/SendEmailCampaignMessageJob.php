<?php

declare(strict_types=1);

namespace App\Jobs\EmailCampaign;

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use App\Mail\EmailCampaignMessage;
use App\Models\EmailCampaignRecipient;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one campaign email. Takes an id rather than a model so the queue
 * payload stays small and the row is always read fresh at send time, and
 * guards on status so a replayed job cannot double-send.
 */
class SendEmailCampaignMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    public function __construct(public string $recipientId)
    {
        $this->onQueue('mail-campaign');
    }

    public function handle(WorkspaceMailerResolver $mailerResolver): void
    {
        $recipient = EmailCampaignRecipient::query()
            ->with(['campaign.workspace', 'contact'])
            ->find($this->recipientId);

        if ($recipient === null || $recipient->status !== RecipientStatus::Queued) {
            return;
        }

        $campaign = $recipient->campaign;

        if ($campaign === null || $campaign->status === Status::Paused || $campaign->status === Status::Cancelled) {
            return;
        }

        $unsubscribeUrl = route('email.unsubscribe', $recipient->unsubscribe_token);

        try {
            $mailer = $mailerResolver->resolve($campaign->workspace);

            $message = new EmailCampaignMessage($campaign, $recipient, $unsubscribeUrl);
            $sent = $mailer->to($recipient->email, $recipient->name)->send($message);

            $recipient->update([
                'status' => RecipientStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
                'provider_message_id' => $this->providerMessageId($sent),
            ]);
        } catch (Throwable $e) {
            Log::warning('Email campaign send failed', [
                'recipient_id' => $recipient->id,
                'campaign_id' => $campaign->id,
                'message' => $e->getMessage(),
            ]);

            $recipient->update([
                'status' => RecipientStatus::Failed,
                'failed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }

        $this->refreshCampaignStatus($recipient);
    }

    public function failed(?Throwable $exception): void
    {
        EmailCampaignRecipient::query()
            ->whereKey($this->recipientId)
            ->where('status', RecipientStatus::Queued)
            ->update([
                'status' => RecipientStatus::Failed->value,
                'failed_at' => now(),
                'error_message' => $exception?->getMessage(),
            ]);
    }

    /**
     * SendKit stamps the provider id on the message after a successful send;
     * it is how delivery webhooks later find this row. Other SMTP transports
     * do not set it, so a null here is expected rather than an error.
     */
    private function providerMessageId(mixed $sent): ?string
    {
        if (! $sent instanceof SentMessage) {
            return null;
        }

        $header = $sent->getOriginalMessage()->getHeaders()->get('X-SendKit-Email-Id');

        return $header?->getBodyAsString() ?: null;
    }

    private function refreshCampaignStatus(EmailCampaignRecipient $recipient): void
    {
        $campaign = $recipient->campaign;

        if ($campaign === null) {
            return;
        }

        $stats = $campaign->recalculateStats();
        $updates = ['stats' => $stats];

        if ($stats['queued'] === 0 && $campaign->status === Status::Sending) {
            $updates['status'] = Status::Completed;
            $updates['completed_at'] = now();
        }

        $campaign->update($updates);
    }
}
