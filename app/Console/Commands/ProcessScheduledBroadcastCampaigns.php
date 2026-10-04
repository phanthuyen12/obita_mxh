<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BroadcastCampaign;
use App\Models\CustomerSegment;
use App\Models\OmnichatContact;
use App\Services\Omnichat\BroadcastCampaignDispatcher;
use App\Services\Omnichat\CustomerSegmentFilterService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omnichat:process-scheduled-broadcasts')]
#[Description('Quét và thực thi các chiến dịch gửi tin nhắn hàng loạt theo lịch hẹn (Set time)')]
class ProcessScheduledBroadcastCampaigns extends Command
{
    public function handle(
        CustomerSegmentFilterService $filterService,
        BroadcastCampaignDispatcher $dispatcher,
    ): int {
        $dueCampaigns = BroadcastCampaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($dueCampaigns->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($dueCampaigns as $campaign) {
            $workspaceId = $campaign->workspace_id;
            $triggerType = $campaign->trigger_type;

            $contactsQuery = OmnichatContact::query()->where('workspace_id', $workspaceId);

            if ($triggerType === 'inactive_30d') {
                $contactsQuery = $filterService->queryInactiveContacts($workspaceId, 30);
            } elseif ($triggerType === 'inactive_40d') {
                $contactsQuery = $filterService->queryInactiveContacts($workspaceId, 40);
            } elseif ($triggerType === 'has_phone') {
                $contactsQuery->whereNotNull('phone')->where('phone', '!=', '');
            } elseif ($triggerType === 'segment' && ! empty($campaign->customer_segment_id)) {
                $segment = CustomerSegment::query()->where('workspace_id', $workspaceId)->whereKey($campaign->customer_segment_id)->first();
                if ($segment) {
                    $contactsQuery = $filterService->queryForSegment($segment);
                }
            }

            $contacts = $contactsQuery->take(500)->get();

            $nextScheduledAt = $campaign->scheduled_at?->copy()->addDay();
            while ($campaign->repeat_daily && $nextScheduledAt?->lessThanOrEqualTo(now())) {
                $nextScheduledAt->addDay();
            }

            if ($campaign->repeat_daily) {
                $campaign->update(['scheduled_at' => $nextScheduledAt]);
            }

            $queuedCount = $dispatcher->createAndQueueMessages($campaign, $contacts);

            $this->info("Chiến dịch '{$campaign->name}' đã đưa {$queuedCount} tin nhắn vào hàng chờ gửi.");
        }

        return self::SUCCESS;
    }
}
