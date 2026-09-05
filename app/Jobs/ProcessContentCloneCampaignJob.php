<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContentCloneCampaign;
use App\Services\ContentCloneCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessContentCloneCampaignJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContentCloneCampaign $campaign) {}

    /**
     * Execute the job.
     */
    public function handle(ContentCloneCampaignService $service): void
    {
        $service->executeCampaignRun($this->campaign);
    }
}
