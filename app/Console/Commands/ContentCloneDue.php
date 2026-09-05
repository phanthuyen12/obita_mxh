<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContentCloneCampaignService;
use Illuminate\Console\Command;

class ContentCloneDue extends Command
{
    protected $signature = 'content:clone-due';

    protected $description = 'Generate due content-clone posts and send them for approval';

    public function handle(ContentCloneCampaignService $service): int
    {
        $processed = $service->processDue();
        $this->info("Processed {$processed} content clone slot(s).");

        return self::SUCCESS;
    }
}
