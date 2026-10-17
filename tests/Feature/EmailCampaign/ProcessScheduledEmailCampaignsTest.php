<?php

declare(strict_types=1);

use App\Enums\EmailCampaign\Status;
use App\Jobs\EmailCampaign\SendEmailCampaignMessageJob;
use App\Models\EmailCampaign;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->list = EmailContactList::factory()->create(['workspace_id' => $this->workspace->id]);
    EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'khach@example.com',
    ]);
});

it('sends a campaign whose scheduled time has passed', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    $campaign = EmailCampaign::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
    ]);

    $this->artisan('email:process-scheduled-campaigns')->assertSuccessful();

    Queue::assertPushed(SendEmailCampaignMessageJob::class, 1);
    expect($campaign->fresh()->status)->toBe(Status::Sending);
});

it('leaves a future campaign alone', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    $campaign = EmailCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
        'status' => Status::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);

    $this->artisan('email:process-scheduled-campaigns')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($campaign->fresh()->status)->toBe(Status::Scheduled);
});

it('marks a campaign failed when the workspace has no smtp configuration', function (): void {
    Queue::fake();

    $campaign = EmailCampaign::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
    ]);

    $this->artisan('email:process-scheduled-campaigns')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($campaign->fresh()->status)->toBe(Status::Failed);
});
