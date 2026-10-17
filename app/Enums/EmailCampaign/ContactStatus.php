<?php

declare(strict_types=1);

namespace App\Enums\EmailCampaign;

enum ContactStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('email_campaigns.contact_status.active'),
            self::Unsubscribed => __('email_campaigns.contact_status.unsubscribed'),
            self::Bounced => __('email_campaigns.contact_status.bounced'),
            self::Complained => __('email_campaigns.contact_status.complained'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Unsubscribed => 'gray',
            self::Bounced => 'red',
            self::Complained => 'red',
        };
    }
}
