<?php

declare(strict_types=1);

namespace App\Enums\EmailCampaign;

enum RecipientStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Bounced = 'bounced';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('email_campaigns.recipient_status.queued'),
            self::Sent => __('email_campaigns.recipient_status.sent'),
            self::Delivered => __('email_campaigns.recipient_status.delivered'),
            self::Opened => __('email_campaigns.recipient_status.opened'),
            self::Clicked => __('email_campaigns.recipient_status.clicked'),
            self::Bounced => __('email_campaigns.recipient_status.bounced'),
            self::Failed => __('email_campaigns.recipient_status.failed'),
            self::Skipped => __('email_campaigns.recipient_status.skipped'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'blue',
            self::Delivered => 'green',
            self::Opened => 'green',
            self::Clicked => 'green',
            self::Bounced => 'red',
            self::Failed => 'red',
            self::Skipped => 'orange',
        };
    }

    /**
     * Rows that still count as work the campaign is waiting on.
     */
    public function isPending(): bool
    {
        return $this === self::Queued;
    }
}
