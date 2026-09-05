<?php

declare(strict_types=1);

namespace App\Jobs\PostAnalytics;

use App\Enums\SocialAccount\Platform;
use App\Events\AccountSyncCompleted;
use App\Events\AccountSyncFailed;
use App\Events\AccountSyncStarted;
use App\Models\SocialAccount;
use App\Services\Social\FacebookPostSyncService;
use App\Services\Social\TikTokPostSyncService;
use App\Services\Social\YouTubePostSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAccountAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public function __construct(
        public SocialAccount $account,
        public string $userId,
    ) {}

    public function handle(
        FacebookPostSyncService $facebookPostSync,
        YouTubePostSyncService $youTubePostSync,
        TikTokPostSyncService $tikTokPostSync,
    ): void {
        AccountSyncStarted::dispatch($this->account);

        try {
            match ($this->account->platform) {
                Platform::Facebook => $facebookPostSync->sync($this->account, $this->userId),
                Platform::YouTube => $youTubePostSync->sync($this->account, $this->userId),
                Platform::TikTok => $tikTokPostSync->sync($this->account, $this->userId),
                default => null,
            };

            Cache::forget("account:syncing:{$this->account->id}");
            AccountSyncCompleted::dispatch($this->account);
        } catch (Throwable $e) {
            Log::error("Failed to sync post analytics for account {$this->account->id}", [
                'error' => $e->getMessage(),
                'platform' => $this->account->platform->value ?? 'unknown',
            ]);
            Cache::forget("account:syncing:{$this->account->id}");
            AccountSyncFailed::dispatch($this->account, $e->getMessage());
            throw $e;
        }
    }
}
