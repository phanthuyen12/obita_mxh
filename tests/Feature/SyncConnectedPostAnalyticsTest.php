<?php

declare(strict_types=1);

use App\Console\Commands\SyncConnectedPostAnalytics;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\FacebookPostSyncService;
use App\Services\Social\TikTokPostSyncService;
use App\Services\Social\YouTubePostSyncService;

it('syncs every active connected analytics account', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $facebook = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
        'connected_by_user_id' => $user->id,
        'is_active' => true,
        'status' => Status::Connected,
    ]);
    $youtube = SocialAccount::factory()->youtube()->create([
        'workspace_id' => $workspace->id,
        'connected_by_user_id' => $user->id,
        'is_active' => true,
        'status' => Status::Connected,
    ]);
    SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $workspace->id,
        'connected_by_user_id' => $user->id,
        'is_active' => false,
        'status' => Status::Connected,
    ]);
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Instagram,
        'connected_by_user_id' => $user->id,
        'is_active' => true,
        'status' => Status::Connected,
    ]);

    $facebookSync = Mockery::mock(FacebookPostSyncService::class);
    $facebookSync->shouldReceive('sync')->once()->withArgs(fn (SocialAccount $account, string $userId): bool => $account->is($facebook) && $userId === $user->id)->andReturn(['fetched' => 0, 'imported' => 0, 'updated' => 0]);
    $youtubeSync = Mockery::mock(YouTubePostSyncService::class);
    $youtubeSync->shouldReceive('sync')->once()->withArgs(fn (SocialAccount $account, string $userId): bool => $account->is($youtube) && $userId === $user->id)->andReturn(['fetched' => 0, 'imported' => 0, 'updated' => 0]);
    $tikTokSync = Mockery::mock(TikTokPostSyncService::class);
    $tikTokSync->shouldNotReceive('sync');
    app()->instance(FacebookPostSyncService::class, $facebookSync);
    app()->instance(YouTubePostSyncService::class, $youtubeSync);
    app()->instance(TikTokPostSyncService::class, $tikTokSync);

    $this->artisan(SyncConnectedPostAnalytics::class)
        ->expectsOutput('Synced 2 connected account(s); 0 failed or skipped.')
        ->assertSuccessful();
});
