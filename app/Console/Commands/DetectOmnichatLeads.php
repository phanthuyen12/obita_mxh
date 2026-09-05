<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Omnichat\DetectPhoneNumberFromMessage;
use App\Models\OmnichatMessage;
use Illuminate\Console\Command;

class DetectOmnichatLeads extends Command
{
    protected $signature = 'omnichat:detect-leads {--workspace= : Only scan one workspace UUID}';

    protected $description = 'Detect phone numbers in historical inbound Omnichat messages and mark contacts as leads';

    public function handle(DetectPhoneNumberFromMessage $detector): int
    {
        $detected = 0;
        $scanned = 0;

        $query = OmnichatMessage::query()
            ->where('direction', 'inbound')
            ->whereNotNull('body')
            ->whereNotNull('sender_contact_id')
            ->whereHas('senderContact', fn ($query) => $query->where('is_lead', false))
            ->when(
                filled($this->option('workspace')),
                fn ($query) => $query->where('workspace_id', $this->option('workspace')),
            );

        foreach ($query->lazyById(200) as $message) {
            $scanned++;
            if ($detector->execute($message) !== null) {
                $detected++;
            }
        }

        $this->components->info("Scanned {$scanned} inbound messages; detected {$detected} leads.");

        return self::SUCCESS;
    }
}
