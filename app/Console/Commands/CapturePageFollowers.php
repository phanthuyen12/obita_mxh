<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialAccount\Platform;
use App\Models\PageFollowerSnapshot;
use App\Models\SocialAccount;
use App\Services\Social\FacebookAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CapturePageFollowers extends Command
{
    protected $signature = 'social:capture-followers';

    protected $description = 'Capture daily follower count for connected social pages (Facebook Insights)';

    public function handle(FacebookAnalytics $facebookAnalytics): int
    {
        $today = now()->toDateString();
        $captured = 0;

        $accounts = SocialAccount::query()
            ->where('platform', Platform::Facebook)
            ->where('is_active', true)
            ->get();

        foreach ($accounts as $account) {
            try {
                $metrics = $facebookAnalytics->getMetrics($account, now()->subDays(1), now());
                $followerMetric = collect($metrics)->firstWhere('label', __('analytics.metrics.page_followers'));
                $followerCount = (int) ($followerMetric['value'] ?? 0);

                PageFollowerSnapshot::query()->updateOrCreate(
                    [
                        'social_account_id' => $account->id,
                        'date' => $today,
                    ],
                    [
                        'follower_count' => $followerCount,
                        'captured_at' => now(),
                    ],
                );
                $captured++;
            } catch (\Throwable $e) {
                Log::warning("Failed to capture followers for page {$account->id}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully captured followers for {$captured} Facebook pages.");

        return self::SUCCESS;
    }
}
