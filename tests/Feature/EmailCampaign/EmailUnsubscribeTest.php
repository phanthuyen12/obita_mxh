<?php

declare(strict_types=1);

use App\Enums\EmailCampaign\ContactStatus;
use App\Enums\EmailCampaign\SuppressionReason;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use App\Models\EmailSuppression;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->list = EmailContactList::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->contact = EmailContact::factory()->create([
        'email_contact_list_id' => $this->list->id,
        'email' => 'nguoinhan@example.com',
    ]);
    $this->campaign = EmailCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_contact_list_id' => $this->list->id,
    ]);
    $this->recipient = EmailCampaignRecipient::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email_campaign_id' => $this->campaign->id,
        'email_contact_id' => $this->contact->id,
        'email' => 'nguoinhan@example.com',
        'unsubscribe_token' => 'token-kiem-tra',
    ]);
});

it('unsubscribes without a session and records a suppression', function (): void {
    $this->get(route('email.unsubscribe', 'token-kiem-tra'))
        ->assertOk()
        ->assertSee('nguoinhan@example.com');

    expect($this->contact->fresh()->status)->toBe(ContactStatus::Unsubscribed);

    $suppression = EmailSuppression::query()
        ->where('workspace_id', $this->workspace->id)
        ->where('email', 'nguoinhan@example.com')
        ->firstOrFail();

    expect($suppression->reason)->toBe(SuppressionReason::Unsubscribed)
        ->and($suppression->source_campaign_id)->toBe($this->campaign->id);
});

it('stays on one suppression row when the link is opened twice', function (): void {
    $this->get(route('email.unsubscribe', 'token-kiem-tra'))->assertOk();
    $this->get(route('email.unsubscribe', 'token-kiem-tra'))->assertOk();

    expect(EmailSuppression::query()->count())->toBe(1);
});

it('shows a friendly page for an unknown token', function (): void {
    $this->get(route('email.unsubscribe', 'khong-ton-tai'))
        ->assertOk()
        ->assertSee('Không tìm thấy liên kết');

    expect(EmailSuppression::query()->count())->toBe(0);
});

it('supports one-click POST unsubscribe', function (): void {
    $this->post(route('email.unsubscribe.store', 'token-kiem-tra'))
        ->assertRedirect(route('email.unsubscribe', 'token-kiem-tra'));

    expect($this->contact->fresh()->status)->toBe(ContactStatus::Unsubscribed);
});
