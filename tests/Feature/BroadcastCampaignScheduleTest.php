<?php

declare(strict_types=1);

use App\Models\BroadcastCampaign;
use App\Models\OmnichatContact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
});

it('runs due daily campaigns and schedules their next run for the following day', function (): void {
    Carbon::setTestNow('2026-10-04 09:00:00');

    $campaign = BroadcastCampaign::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Daily offer',
        'trigger_type' => 'all',
        'message_template' => 'Hello {name}',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'repeat_daily' => true,
    ]);
    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Customer',
    ]);

    $this->artisan('omnichat:process-scheduled-broadcasts')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('scheduled')
        ->and($campaign->fresh()->scheduled_at->toDateTimeString())->toBe('2026-10-05 08:59:00')
        ->and($campaign->messages()->where('contact_id', $contact->id)->where('status', 'sent')->exists())->toBeTrue();
});

it('completes one-time campaigns after their scheduled run', function (): void {
    Carbon::setTestNow('2026-10-04 09:00:00');

    $campaign = BroadcastCampaign::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'One-time offer',
        'trigger_type' => 'all',
        'message_template' => 'Hello',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('omnichat:process-scheduled-broadcasts')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});
