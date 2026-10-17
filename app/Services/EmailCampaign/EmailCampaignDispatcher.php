<?php

declare(strict_types=1);

namespace App\Services\EmailCampaign;

use App\Enums\EmailCampaign\ContactStatus;
use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use App\Jobs\EmailCampaign\SendEmailCampaignMessageJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContact;
use App\Models\EmailSuppression;
use App\Services\Omnichat\BroadcastCampaignDispatcher;
use Illuminate\Support\Str;

/**
 * Turns a campaign plus its contact list into per-recipient rows, then queues
 * one send job per row with a staggered delay (same shape as
 * {@see BroadcastCampaignDispatcher}).
 */
class EmailCampaignDispatcher
{
    private const INSERT_CHUNK = 500;

    /**
     * Build the recipient rows for a campaign. Suppressed or non-active
     * contacts are still written, but marked Skipped so the operator can see
     * exactly who was excluded and why.
     *
     * @return array{queued: int, skipped: int}
     */
    public function materializeRecipients(EmailCampaign $campaign): array
    {
        $list = $campaign->contactList;

        if ($list === null) {
            return ['queued' => 0, 'skipped' => 0];
        }

        $suppressed = EmailSuppression::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->flip();

        $existing = $campaign->recipients()->pluck('email')->map(
            fn (string $email): string => mb_strtolower($email)
        )->flip();

        $queued = 0;
        $skipped = 0;
        $now = now();

        $list->contacts()->chunkById(self::INSERT_CHUNK, function ($contacts) use (
            $campaign, $suppressed, $existing, $now, &$queued, &$skipped
        ): void {
            $rows = [];

            foreach ($contacts as $contact) {
                /** @var EmailContact $contact */
                $email = mb_strtolower($contact->email);

                if ($existing->has($email)) {
                    continue;
                }

                $isSkipped = $suppressed->has($email) || $contact->status !== ContactStatus::Active;
                $isSkipped ? $skipped++ : $queued++;

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $campaign->workspace_id,
                    'email_campaign_id' => $campaign->id,
                    'email_contact_id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'status' => $isSkipped ? RecipientStatus::Skipped->value : RecipientStatus::Queued->value,
                    'unsubscribe_token' => Str::random(48),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                EmailCampaignRecipient::query()->insert($rows);
            }
        });

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Queue every pending recipient, spacing sends out by the campaign delay.
     */
    public function dispatch(EmailCampaign $campaign): int
    {
        $campaign->update([
            'status' => Status::Sending,
            'started_at' => $campaign->started_at ?? now(),
            'completed_at' => null,
        ]);

        $index = 0;
        $delaySeconds = max(1, $campaign->delay_seconds);

        $campaign->recipients()
            ->where('status', RecipientStatus::Queued)
            ->orderBy('id')
            ->chunkById(self::INSERT_CHUNK, function ($recipients) use ($delaySeconds, &$index): void {
                foreach ($recipients as $recipient) {
                    SendEmailCampaignMessageJob::dispatch($recipient->id)
                        ->delay(now()->addSeconds($index * $delaySeconds));
                    $index++;
                }
            });

        if ($index === 0) {
            $campaign->update([
                'status' => Status::Completed,
                'completed_at' => now(),
                'stats' => $campaign->recalculateStats(),
            ]);

            return 0;
        }

        $campaign->update(['stats' => $campaign->recalculateStats()]);

        return $index;
    }

    /**
     * Re-queue the rows that previously failed, for a one-click retry.
     */
    public function retryFailed(EmailCampaign $campaign): int
    {
        $campaign->recipients()
            ->whereIn('status', [RecipientStatus::Failed, RecipientStatus::Bounced])
            ->update([
                'status' => RecipientStatus::Queued,
                'error_message' => null,
                'failed_at' => null,
            ]);

        return $this->dispatch($campaign);
    }
}
