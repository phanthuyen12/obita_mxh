<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Services\Social\FacebookPostSyncService;
use App\Services\Social\TikTokPostSyncService;
use App\Services\Social\YouTubePostSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncConnectedPostAnalytics extends Command
{
    protected $signature = 'posts:sync-connected-analytics';

    protected $description = 'Sync posts and metric snapshots for all active connected Facebook, YouTube, and TikTok accounts';

    public function handle(
        FacebookPostSyncService $facebookPostSync,
        YouTubePostSyncService $youTubePostSync,
        TikTokPostSyncService $tikTokPostSync,
    ): int {
        $synced = 0;
        $failed = 0;

        SocialAccount::query()
            ->where('is_active', true)
            ->where('status', Status::Connected)
            ->whereIn('platform', [Platform::Facebook, Platform::YouTube, Platform::TikTok])
            ->with('workspace:id,user_id')
            ->lazyById(100)
            ->each(function (SocialAccount $account) use ($facebookPostSync, $youTubePostSync, $tikTokPostSync, &$synced, &$failed): void {
                $userId = $account->connected_by_user_id ?? $account->workspace?->user_id;
                if ($userId === null) {
                    $failed++;
                    Log::warning('Skipping connected analytics sync without a workspace owner', ['social_account_id' => $account->id]);

                    return;
                }

                try {
                    match ($account->platform) {
                        Platform::Facebook => $facebookPostSync->sync($account, $userId),
                        Platform::YouTube => $youTubePostSync->sync($account, $userId),
                        Platform::TikTok => $tikTokPostSync->sync($account, $userId),
                    };
                    $synced++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Connected analytics sync failed', [
                        'social_account_id' => $account->id,
                        'platform' => $account->platform->value,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Synced {$synced} connected account(s); {$failed} failed or skipped.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
