<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\CustomerSegment;
use App\Models\OmnichatContact;
use App\Services\Omnichat\CustomerSegmentFilterService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omnichat:process-scheduled-broadcasts')]
#[Description('Quét và thực thi các chiến dịch gửi tin nhắn hàng loạt theo lịch hẹn (Set time)')]
class ProcessScheduledBroadcastCampaigns extends Command
{
    public function handle(CustomerSegmentFilterService $filterService): int
    {
        $dueCampaigns = BroadcastCampaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($dueCampaigns->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($dueCampaigns as $campaign) {
            $campaign->update([
                'status' => 'sending',
                'started_at' => now(),
            ]);

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

            $sentCount = 0;
            foreach ($contacts as $contact) {
                $customerName = $contact->name ?: $contact->display_name ?: 'anh/chị';
                $phone = $contact->phone ?: '';

                $personalizedBody = str_replace(
                    ['{name}', '{ho_ten}', '{phone}', '{sdt}'],
                    [$customerName, $customerName, $phone, $phone],
                    $campaign->message_template
                );

                $conversation = $contact->conversations()->latest()->first();

                BroadcastMessage::query()->create([
                    'workspace_id' => $workspaceId,
                    'broadcast_campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation?->id,
                    'sent_body' => $personalizedBody,
                    'image_url' => $campaign->image_url,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $sentCount++;
            }

            $nextScheduledAt = $campaign->scheduled_at?->copy()->addDay();
            while ($campaign->repeat_daily && $nextScheduledAt?->lessThanOrEqualTo(now())) {
                $nextScheduledAt->addDay();
            }

            $campaign->update([
                'status' => $campaign->repeat_daily ? 'scheduled' : 'completed',
                'scheduled_at' => $campaign->repeat_daily ? $nextScheduledAt : $campaign->scheduled_at,
                'completed_at' => $campaign->repeat_daily ? null : now(),
                'stats' => [
                    'total_targeted' => $contacts->count(),
                    'total_sent' => $sentCount,
                    'failed' => 0,
                ],
            ]);

            $this->info("Chiến dịch '{$campaign->name}' đã gửi thành công tới {$sentCount} khách hàng.");
        }

        return self::SUCCESS;
    }
}
