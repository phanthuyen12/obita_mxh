<?php

declare(strict_types=1);

use App\Console\Commands\Automation\FireScheduleTriggers;
use App\Console\Commands\Automation\ProcessAutomationDelays;
use App\Console\Commands\Automation\PruneDryRunAutomationRuns;
use App\Console\Commands\Automation\RecoverStuckAutomationRuns;
use App\Console\Commands\CapturePageFollowers;
use App\Console\Commands\CapturePostMetrics;
use App\Console\Commands\CheckSocialConnections;
use App\Console\Commands\CheckUpcomingPostConnections;
use App\Console\Commands\ContentCloneDue;
use App\Console\Commands\ProcessScheduledPosts;
use App\Console\Commands\RecoverStuckPosts;
use App\Console\Commands\RefreshExpiringTokens;
use App\Console\Commands\SyncConnectedPostAnalytics;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessScheduledPosts::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(CheckSocialConnections::class)->daily()->withoutOverlapping()->onOneServer();
Schedule::command(CheckUpcomingPostConnections::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(RefreshExpiringTokens::class)->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(RecoverStuckPosts::class)->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(FireScheduleTriggers::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(ProcessAutomationDelays::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(RecoverStuckAutomationRuns::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command(PruneDryRunAutomationRuns::class)->everyTenMinutes()->withoutOverlapping()->onOneServer();

// Automated Growth & Analytics Cron Schedules (Configured in config/trypost.php or .env)
Schedule::command(CapturePostMetrics::class)
    ->cron((string) config('trypost.analytics.metrics_capture_cron', '*/30 * * * *'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(CapturePageFollowers::class)
    ->cron((string) config('trypost.analytics.followers_capture_cron', '5 0 * * *'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(SyncConnectedPostAnalytics::class)
    ->cron((string) config('trypost.analytics.sync_cron', '0 */6 * * *'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(ContentCloneDue::class)->everyMinute()->withoutOverlapping()->onOneServer();
