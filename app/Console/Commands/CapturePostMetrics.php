<?php

namespace App\Console\Commands;

use App\Enums\Post\Status as PostStatus;
use App\Models\PostMetricSnapshot;
use App\Models\PostPlatform;
use App\Services\Post\PostMetricsFetcher;
use Illuminate\Console\Command;

class CapturePostMetrics extends Command
{
    protected $signature = 'posts:capture-metrics';

    protected $description = 'Capture published post metrics for growth analytics';

    public function handle(PostMetricsFetcher $metricsFetcher): int
    {
        $capturedAt = now()->startOfMinute();
        $captured = 0;

        PostPlatform::query()
            ->whereIn('status', ['published'])
            ->whereHas('post', fn ($query) => $query
                ->whereIn('status', [PostStatus::Published, PostStatus::PartiallyPublished])
                ->where('published_at', '>=', now()->subWeeks(4)))
            ->with('socialAccount')
            ->lazy()
            ->each(function ($platform) use ($metricsFetcher, $capturedAt, &$captured): void {
                $metrics = $metricsFetcher->forPlatform($platform);
                if (isset($metrics['unsupported'])) {
                    return;
                }

                PostMetricSnapshot::query()->updateOrCreate(
                    ['post_platform_id' => $platform->id, 'captured_at' => $capturedAt],
                    ['metrics' => $metrics],
                );
                $captured++;
            });

        $this->info("Captured {$captured} post metric snapshots.");

        return self::SUCCESS;
    }
}
