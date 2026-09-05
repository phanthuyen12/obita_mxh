<?php

declare(strict_types=1);

namespace App\Listeners\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Jobs\Omnichat\DeliverWebsiteChatWebhook;

class DispatchWebsiteChatWebhook
{
    public function handle(OmnichatMessageCreated $event): void
    {
        if ($event->message->direction !== 'outbound' || $event->message->channel_id === null) {
            return;
        }

        DeliverWebsiteChatWebhook::dispatch('message.created', $event->message->channel_id, $event->message->id);
    }
}
