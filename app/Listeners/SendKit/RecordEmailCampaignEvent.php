<?php

declare(strict_types=1);

namespace App\Listeners\SendKit;

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\SuppressionReason;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSuppression;

/**
 * Maps SendKit delivery webhooks back onto campaign recipient rows.
 *
 * Only fires for mail sent through the SendKit transport. Workspaces using
 * their own SMTP get no callbacks, so their recipients stop at `sent`.
 */
class RecordEmailCampaignEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $type, array $payload): void
    {
        $messageId = data_get($payload, 'email_id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'message_id');

        if (! is_string($messageId) || $messageId === '') {
            return;
        }

        $recipient = EmailCampaignRecipient::query()
            ->where('provider_message_id', $messageId)
            ->first();

        if ($recipient === null) {
            return;
        }

        match ($type) {
            'delivered' => $this->markDelivered($recipient),
            'opened' => $this->markOpened($recipient),
            'clicked' => $this->markClicked($recipient),
            'bounced' => $this->markSuppressed($recipient, RecipientStatus::Bounced, SuppressionReason::Bounced, $payload),
            'complained' => $this->markSuppressed($recipient, RecipientStatus::Bounced, SuppressionReason::Complained, $payload),
            'failed' => $this->markFailed($recipient, $payload),
            default => null,
        };

        $recipient->campaign?->update(['stats' => $recipient->campaign->recalculateStats()]);
    }

    private function markDelivered(EmailCampaignRecipient $recipient): void
    {
        if (in_array($recipient->status, [RecipientStatus::Opened, RecipientStatus::Clicked], true)) {
            return;
        }

        $recipient->update(['status' => RecipientStatus::Delivered, 'delivered_at' => now()]);
    }

    private function markOpened(EmailCampaignRecipient $recipient): void
    {
        if ($recipient->status === RecipientStatus::Clicked) {
            $recipient->update(['opened_at' => $recipient->opened_at ?? now()]);

            return;
        }

        $recipient->update([
            'status' => RecipientStatus::Opened,
            'opened_at' => $recipient->opened_at ?? now(),
            'delivered_at' => $recipient->delivered_at ?? now(),
        ]);
    }

    private function markClicked(EmailCampaignRecipient $recipient): void
    {
        $recipient->update([
            'status' => RecipientStatus::Clicked,
            'clicked_at' => $recipient->clicked_at ?? now(),
            'opened_at' => $recipient->opened_at ?? now(),
            'delivered_at' => $recipient->delivered_at ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markSuppressed(
        EmailCampaignRecipient $recipient,
        RecipientStatus $status,
        SuppressionReason $reason,
        array $payload,
    ): void {
        $recipient->update([
            'status' => $status,
            'failed_at' => now(),
            'error_message' => $this->reasonFrom($payload),
        ]);

        EmailSuppression::query()->updateOrCreate(
            ['workspace_id' => $recipient->workspace_id, 'email' => $recipient->email],
            ['reason' => $reason, 'source_campaign_id' => $recipient->email_campaign_id],
        );

        $recipient->contact?->update(['status' => $reason->toContactStatus()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(EmailCampaignRecipient $recipient, array $payload): void
    {
        $recipient->update([
            'status' => RecipientStatus::Failed,
            'failed_at' => now(),
            'error_message' => $this->reasonFrom($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reasonFrom(array $payload): ?string
    {
        $reason = data_get($payload, 'reason') ?? data_get($payload, 'message') ?? data_get($payload, 'description');

        return is_string($reason) ? mb_substr($reason, 0, 1000) : null;
    }
}
