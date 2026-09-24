<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OmnichatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WebsiteChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OmnichatMessage $message) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $conversation = $this->message->conversation;
        $name = $this->message->senderContact?->display_name ?? 'Khách hàng';
        $body = trim((string) $this->message->body);
        $provider = $this->message->channel?->provider?->value ?? 'omnichat';

        return (new WebPushMessage)
            ->title("Tin nhắn mới từ {$name}")
            ->body($body !== '' ? $body : 'Khách hàng đã gửi một tin nhắn mới.')
            ->icon('/apple-touch-icon.png')
            ->badge('/favicon-32x32.png')
            ->tag("omnichat-{$conversation?->id}")
            ->data([
                'url' => '/omnichat/livechat',
                'provider' => $provider,
            ]);
    }
}
