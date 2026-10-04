<?php

declare(strict_types=1);

namespace App\Services\Omnichat;

use App\Jobs\Omnichat\SendBroadcastMessageJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\OmnichatContact;
use Illuminate\Support\Collection;

class BroadcastCampaignDispatcher
{
    /** @param Collection<int, OmnichatContact> $contacts */
    public function createAndQueueMessages(BroadcastCampaign $campaign, Collection $contacts): int
    {
        $messages = $contacts->map(function (OmnichatContact $contact) use ($campaign): BroadcastMessage {
            $customerName = $contact->name ?: $contact->display_name ?: 'anh/chị';
            $phone = $contact->phone ?: '';
            $personalizedBody = str_replace(
                ['{name}', '{ho_ten}', '{phone}', '{sdt}'],
                [$customerName, $customerName, $phone, $phone],
                $campaign->message_template,
            );
            $conversation = $contact->conversations()->latest()->first();

            return BroadcastMessage::query()->create([
                'workspace_id' => $campaign->workspace_id,
                'broadcast_campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation?->id,
                'sent_body' => $personalizedBody,
                'image_url' => $campaign->image_url,
                'status' => 'queued',
            ]);
        });

        $campaign->update([
            'status' => $messages->isEmpty() ? ($campaign->repeat_daily ? 'scheduled' : 'completed') : 'sending',
            'started_at' => $campaign->started_at ?? now(),
            'completed_at' => $messages->isEmpty() && ! $campaign->repeat_daily ? now() : null,
            'stats' => [
                'total_targeted' => $messages->count(),
                'total_sent' => 0,
                'failed' => 0,
                'pending' => $messages->count(),
            ],
        ]);

        $this->queueMessages($campaign, $messages);

        return $messages->count();
    }

    /** @param Collection<int, BroadcastMessage> $messages */
    public function queueMessages(BroadcastCampaign $campaign, Collection $messages): void
    {
        $campaign->update([
            'status' => $messages->isEmpty()
                ? ($campaign->repeat_daily ? 'scheduled' : 'completed')
                : 'sending',
            'started_at' => $campaign->started_at ?? now(),
            'completed_at' => $messages->isEmpty() && ! $campaign->repeat_daily ? now() : null,
        ]);

        foreach ($messages->values() as $index => $message) {
            SendBroadcastMessageJob::dispatch($message->id)
                ->onQueue('broadcasts')
                ->delay(now()->addSeconds($index * max(1, (int) $campaign->delay_seconds)));
        }
    }
}
