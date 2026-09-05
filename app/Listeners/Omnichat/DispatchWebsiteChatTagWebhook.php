<?php

declare(strict_types=1);

namespace App\Listeners\Omnichat;

use App\Events\OmnichatConversationTagged;
use App\Jobs\Omnichat\DeliverWebsiteChatWebhook;

class DispatchWebsiteChatTagWebhook
{
    public function handle(OmnichatConversationTagged $event): void
    {
        if ($event->conversation->channel_id === null) {
            return;
        }

        DeliverWebsiteChatWebhook::dispatch(
            'conversation.tagged',
            $event->conversation->channel_id,
            $event->conversation->id,
            $event->tagIds,
        );
    }
}
