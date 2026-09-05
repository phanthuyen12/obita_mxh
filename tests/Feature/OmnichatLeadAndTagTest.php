<?php

declare(strict_types=1);

use App\Actions\Omnichat\DetectPhoneNumberFromMessage;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'phone' => null,
        'is_lead' => false,
    ]);
    $this->conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->account->id,
        'contact_id' => $this->contact->id,
    ]);
});

it('detects and normalizes a Vietnamese phone number from an inbound message', function (): void {
    $message = OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->account->id,
        'conversation_id' => $this->conversation->id,
        'sender_contact_id' => $this->contact->id,
        'direction' => 'inbound',
        'body' => 'Shop gọi lại cho mình số +84 912 345 678 nhé',
    ]);

    expect(app(DetectPhoneNumberFromMessage::class)->execute($message))->toBe('0912345678')
        ->and($this->contact->fresh()->phone)->toBe('0912345678')
        ->and($this->contact->fresh()->is_lead)->toBeTrue()
        ->and($this->contact->fresh()->lead_stage)->toBe('new')
        ->and($this->contact->fresh()->phone_detected_at)->not->toBeNull()
        ->and($this->conversation->tags()->sole()->name)->toBe('CÓ SĐT')
        ->and($this->conversation->tags()->sole()->color)->toBe('#16A34A');
});

it('ignores phone numbers from outbound and internal messages', function (string $direction): void {
    $message = OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->account->id,
        'conversation_id' => $this->conversation->id,
        'sender_contact_id' => $this->contact->id,
        'direction' => $direction,
        'body' => 'Hotline 0912345678',
    ]);

    expect(app(DetectPhoneNumberFromMessage::class)->execute($message))->toBeNull()
        ->and($this->contact->fresh()->is_lead)->toBeFalse();
})->with(['outbound', 'internal']);

it('shows detected leads and supports filters', function (): void {
    $tag = OmnichatTag::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Quan tâm',
    ]);
    $this->conversation->tags()->attach($tag);
    $this->contact->update([
        'phone' => '0912345678',
        'is_lead' => true,
        'lead_stage' => 'qualified',
        'phone_detected_at' => now(),
    ]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.omnichat.leads.index', [
            'search' => '0912',
            'stage' => 'qualified',
            'provider' => 'facebook',
            'tag' => $tag->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('omnichat/Leads')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $this->contact->id)
            ->where('leads.data.0.phone', '0912345678')
            ->where('leads.data.0.tags.0.id', $tag->id));
});

it('updates customer information and notes', function (): void {
    $this->actingAs($this->user->fresh())
        ->patchJson(route('app.omnichat.leads.update', $this->contact), [
            'display_name' => 'Nguyễn Văn An',
            'email' => 'an@example.com',
            'phone' => '0912345678',
            'notes' => 'Khách quan tâm gói doanh nghiệp.',
        ])
        ->assertOk()
        ->assertJsonPath('lead.display_name', 'Nguyễn Văn An')
        ->assertJsonPath('lead.notes', 'Khách quan tâm gói doanh nghiệp.');

    expect($this->contact->fresh())
        ->display_name->toBe('Nguyễn Văn An')
        ->email->toBe('an@example.com')
        ->phone->toBe('0912345678')
        ->notes->toBe('Khách quan tâm gói doanh nghiệp.');
});

it('does not update customer information from another workspace', function (): void {
    $foreignContact = OmnichatContact::factory()->create();

    $this->actingAs($this->user->fresh())
        ->patchJson(route('app.omnichat.leads.update', $foreignContact), [
            'notes' => 'Không được phép',
        ])
        ->assertForbidden();

    expect($foreignContact->fresh()->notes)->toBeNull();
});

it('creates tags and attaches them to a conversation', function (): void {
    $response = $this->actingAs($this->user->fresh())
        ->postJson(route('app.omnichat.tags.store'), [
            'name' => 'VIP',
            'color' => '#DC2626',
        ])
        ->assertCreated();

    $tagId = $response->json('tag.id');

    $this->putJson(route('app.omnichat.conversations.tags.update', $this->conversation), [
        'tag_ids' => [$tagId],
    ])->assertOk()->assertJsonPath('tags.0.id', $tagId);

    expect($this->conversation->tags()->sole()->name)->toBe('VIP');
});

it('prevents attaching a tag from another workspace', function (): void {
    $foreignTag = OmnichatTag::factory()->create();

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.conversations.tags.update', $this->conversation), [
            'tag_ids' => [$foreignTag->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids.0']);
});
