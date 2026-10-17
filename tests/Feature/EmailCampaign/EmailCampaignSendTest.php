<?php

declare(strict_types=1);

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use App\Enums\UserWorkspace\Role;
use App\Jobs\EmailCampaign\SendEmailCampaignMessageJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->list = EmailContactList::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->campaign = EmailCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
    ]);
});

it('refuses to send when the workspace has no smtp configuration', function (): void {
    Queue::fake();

    EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'a@example.com',
    ]);

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.send', $this->campaign))
        ->assertSessionHasErrors('mail');

    Queue::assertNothingPushed();
    expect($this->campaign->fresh()->status)->toBe(Status::Draft);
});

it('queues one job per contact with a staggered delay', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
        EmailContact::factory()->create([
            'email_contact_list_id' => $this->list->id,
            'email' => $email,
        ]);
    }

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.send', $this->campaign))
        ->assertRedirect();

    Queue::assertPushed(SendEmailCampaignMessageJob::class, 3);

    expect($this->campaign->fresh()->status)->toBe(Status::Sending)
        ->and(EmailCampaignRecipient::query()->count())->toBe(3)
        ->and(EmailCampaignRecipient::query()->whereNull('unsubscribe_token')->count())->toBe(0);
});

it('skips suppressed and unsubscribed addresses instead of mailing them', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'ok@example.com',
    ]);
    EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'blocked@example.com',
    ]);
    EmailContact::factory()->unsubscribed()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'gone@example.com',
    ]);

    EmailSuppression::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'blocked@example.com',
    ]);

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.send', $this->campaign))
        ->assertRedirect();

    Queue::assertPushed(SendEmailCampaignMessageJob::class, 1);

    $skipped = EmailCampaignRecipient::query()
        ->where('status', RecipientStatus::Skipped)
        ->pluck('email')
        ->sort()
        ->values()
        ->all();

    expect($skipped)->toBe(['blocked@example.com', 'gone@example.com']);
});

it('completes immediately when every address is skipped', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    EmailContact::factory()->unsubscribed()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'gone@example.com',
    ]);

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.send', $this->campaign))
        ->assertRedirect();

    Queue::assertNothingPushed();

    expect($this->campaign->fresh()->status)->toBe(Status::Completed)
        ->and($this->campaign->fresh()->stats['skipped'])->toBe(1);
});

it('does not duplicate recipients when send is pressed twice', function (): void {
    Queue::fake();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'a@example.com',
    ]);

    $user = $this->user->fresh();

    $this->actingAs($user)->post(route('app.email-campaigns.send', $this->campaign));
    $this->actingAs($user)->post(route('app.email-campaigns.send', $this->campaign));

    expect(EmailCampaignRecipient::query()->count())->toBe(1);
});
