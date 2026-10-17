<?php

declare(strict_types=1);

namespace App\Enums\EmailCampaign;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('email_campaigns.import_status.pending'),
            self::Importing => __('email_campaigns.import_status.importing'),
            self::Completed => __('email_campaigns.import_status.completed'),
            self::Failed => __('email_campaigns.import_status.failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Importing => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
