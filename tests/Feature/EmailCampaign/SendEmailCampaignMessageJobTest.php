<?php

declare(strict_types=1);

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use App\Jobs\EmailCampaign\SendEmailCampaignMessageJob;
use App\Mail\EmailCampaignMessage;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    WorkspaceMailSetting::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->list = EmailContactList::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->contact = EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'khach@example.com',
        'name' => 'Khách A',
        'custom_fields' => ['city' => 'Hà Nội'],
    ]);
    $this->campaign = EmailCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
        'status' => Status::Sending,
        'subject' => 'Chào {{ name }}',
        'body_html' => '<p>Xin chào {{ name }} ở {{ fields.city }}</p>',
    ]);
    $this->recipient = EmailCampaignRecipient::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_campaign_id' => $this->campaign->id,
        'email_contact_id' => $this->contact->id,
        'email' => 'khach@example.com',
        'name' => 'Khách A',
    ]);
});

it('sends the mail and marks the recipient as sent', function (): void {
    Mail::fake();

    (new SendEmailCampaignMessageJob($this->recipient->id))->handle(
        app(WorkspaceMailerResolver::class),
    );

    Mail::assertSent(
        EmailCampaignMessage::class,
        fn (EmailCampaignMessage $mail): bool => $mail->hasTo('khach@example.com'),
    );

    expect($this->recipient->fresh()->status)->toBe(RecipientStatus::Sent)
        ->and($this->recipient->fresh()->sent_at)->not->toBeNull();
});

it('renders the recipient variables into subject and body', function (): void {
    Mail::fake();

    (new SendEmailCampaignMessageJob($this->recipient->id))->handle(
        app(WorkspaceMailerResolver::class),
    );

    Mail::assertSent(EmailCampaignMessage::class, function (EmailCampaignMessage $mail): bool {
        $mail->assertHasSubject('Chào Khách A');
        $mail->assertSeeInHtml('Xin chào Khách A ở Hà Nội', false);
        $mail->assertSeeInHtml('Hủy nhận email', false);

        return true;
    });
});

it('completes the campaign once no recipient is left queued', function (): void {
    Mail::fake();

    (new SendEmailCampaignMessageJob($this->recipient->id))->handle(
        app(WorkspaceMailerResolver::class),
    );

    expect($this->campaign->fresh()->status)->toBe(Status::Completed)
        ->and($this->campaign->fresh()->stats['sent'])->toBe(1)
        ->and($this->campaign->fresh()->completed_at)->not->toBeNull();
});

it('does not send twice for a recipient that is no longer queued', function (): void {
    Mail::fake();
    $this->recipient->update(['status' => RecipientStatus::Sent]);

    (new SendEmailCampaignMessageJob($this->recipient->id))->handle(
        app(WorkspaceMailerResolver::class),
    );

    Mail::assertNothingSent();
});

it('does not send while the campaign is paused', function (): void {
    Mail::fake();
    $this->campaign->update(['status' => Status::Paused]);

    (new SendEmailCampaignMessageJob($this->recipient->id))->handle(
        app(WorkspaceMailerResolver::class),
    );

    Mail::assertNothingSent();
    expect($this->recipient->fresh()->status)->toBe(RecipientStatus::Queued);
});
