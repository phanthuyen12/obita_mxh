<?php

declare(strict_types=1);

namespace App\Enums\EmailCampaign;

enum Status: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('email_campaigns.status.draft'),
            self::Scheduled => __('email_campaigns.status.scheduled'),
            self::Sending => __('email_campaigns.status.sending'),
            self::Paused => __('email_campaigns.status.paused'),
            self::Completed => __('email_campaigns.status.completed'),
            self::Failed => __('email_campaigns.status.failed'),
            self::Cancelled => __('email_campaigns.status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::Sending => 'yellow',
            self::Paused => 'orange',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Cancelled => 'gray',
        };
    }

    /**
     * A campaign can only be queued for sending from these states.
     */
    public function isDispatchable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Paused], true);
    }
}
