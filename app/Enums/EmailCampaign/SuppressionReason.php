<?php

declare(strict_types=1);

namespace App\Enums\EmailCampaign;

enum SuppressionReason: string
{
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Unsubscribed => __('email_campaigns.suppression_reason.unsubscribed'),
            self::Bounced => __('email_campaigns.suppression_reason.bounced'),
            self::Complained => __('email_campaigns.suppression_reason.complained'),
            self::Manual => __('email_campaigns.suppression_reason.manual'),
        };
    }

    public function toContactStatus(): ContactStatus
    {
        return match ($this) {
            self::Unsubscribed, self::Manual => ContactStatus::Unsubscribed,
            self::Bounced => ContactStatus::Bounced,
            self::Complained => ContactStatus::Complained,
        };
    }
}
