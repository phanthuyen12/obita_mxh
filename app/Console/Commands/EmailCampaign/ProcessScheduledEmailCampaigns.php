<?php

declare(strict_types=1);

namespace App\Console\Commands\EmailCampaign;

use App\Enums\EmailCampaign\Status;
use App\Exceptions\WorkspaceMailNotConfiguredException;
use App\Models\EmailCampaign;
use App\Services\EmailCampaign\EmailCampaignDispatcher;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email:process-scheduled-campaigns')]
#[Description('Quét và gửi các chiến dịch email đã hẹn giờ')]
class ProcessScheduledEmailCampaigns extends Command
{
    private const MAX_PER_RUN = 500;

    public function handle(EmailCampaignDispatcher $dispatcher, WorkspaceMailerResolver $mailerResolver): int
    {
        $dueCampaigns = EmailCampaign::query()
            ->with('workspace')
            ->where('status', Status::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->take(self::MAX_PER_RUN)
            ->get();

        foreach ($dueCampaigns as $campaign) {
            // Claim the row so a second scheduler tick cannot send it twice.
            $claimed = EmailCampaign::query()
                ->whereKey($campaign->id)
                ->where('status', Status::Scheduled)
                ->update(['status' => Status::Sending->value, 'started_at' => now()]);

            if ($claimed === 0) {
                continue;
            }

            try {
                $mailerResolver->settingsFor($campaign->workspace);
            } catch (WorkspaceMailNotConfiguredException $e) {
                $campaign->update(['status' => Status::Failed]);
                $this->error("Campaign {$campaign->id}: {$e->getMessage()}");

                continue;
            }

            $dispatcher->materializeRecipients($campaign);
            $queued = $dispatcher->dispatch($campaign->fresh());

            $this->info("Campaign {$campaign->id}: queued {$queued} emails.");
        }

        return self::SUCCESS;
    }
}
