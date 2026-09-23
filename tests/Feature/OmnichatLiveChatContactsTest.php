<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatTag;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    config(['trypost.self_hosted' => true]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->channel = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

$makeContactWithConversation = function (array $contactAttributes = []): OmnichatContact {
    $contact = OmnichatContact::factory()->create(array_merge([
        'workspace_id' => test()->workspace->id,
    ], $contactAttributes));

    OmnichatConversation::factory()->create([
        'workspace_id' => test()->workspace->id,
        'social_account_id' => test()->channel->id,
        'contact_id' => $contact->id,
    ]);

    return $contact->fresh();
};

it('lists livechat contacts with tags and meta', function () use ($makeContactWithConversation): void {
    $contact = $makeContactWithConversation();

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $contact->id)
        ->assertJsonPath('meta.has_next_page', false)
        ->assertJsonStructure([
            'data' => [['id', 'display_name', 'phone', 'tags', 'conversation_count']],
            'meta' => ['current_page', 'last_page', 'has_next_page', 'total'],
        ]);
});

it('searches livechat contacts by name and phone', function () use ($makeContactWithConversation): void {
    $matching = $makeContactWithConversation(['display_name' => 'Nguyen Van A', 'phone' => '0988123456']);
    $makeContactWithConversation(['display_name' => 'Someone Else']);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.index', ['search' => 'Nguyen']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.index', ['search' => '0988']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('filters livechat contacts that have a phone', function () use ($makeContactWithConversation): void {
    $withPhone = $makeContactWithConversation(['phone' => '0988123456']);
    $makeContactWithConversation(['phone' => null]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.index', ['filter' => 'has_phone']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $withPhone->id);
});

it('filters livechat contacts tagged as vip', function () use ($makeContactWithConversation): void {
    $vipTag = OmnichatTag::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'VIP']);
    $vipContact = $makeContactWithConversation();
    $vipContact->conversations()->first()->tags()->attach($vipTag->id);
    $makeContactWithConversation();

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.index', ['filter' => 'vip']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vipContact->id);
});

it('updates a livechat contact and syncs its tags', function () use ($makeContactWithConversation): void {
    $contact = $makeContactWithConversation();
    $tag = OmnichatTag::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.livechat.contacts.update', $contact), [
            'display_name' => 'Updated Name',
            'notes' => 'VIP customer',
            'tag_ids' => [$tag->id],
        ])
        ->assertOk()
        ->assertJsonPath('contact.display_name', 'Updated Name')
        ->assertJsonPath('contact.notes', 'VIP customer')
        ->assertJsonCount(1, 'tags')
        ->assertJsonPath('tags.0.id', $tag->id);

    $conversation = $contact->conversations()->first();
    expect($conversation->tags->pluck('id'))->toContain($tag->id);
});

it('forbids updating a contact from another workspace', function (): void {
    $foreignContact = OmnichatContact::factory()->create();

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.livechat.contacts.update', $foreignContact), [
            'display_name' => 'Hacked',
        ])
        ->assertForbidden();
});

it('lists workspace tags for the livechat tag sheets', function (): void {
    $tag = OmnichatTag::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.tags.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tag->id);
});

it('creates a new tag from the livechat tag sheet', function (): void {
    $this->actingAs($this->user->fresh())
        ->postJson(route('app.omnichat.livechat.tags.store'), [
            'name' => 'Chờ chuyển khoản',
        ])
        ->assertCreated()
        ->assertJsonPath('tag.name', 'Chờ chuyển khoản');

    expect(OmnichatTag::query()->where('workspace_id', $this->workspace->id)->where('name', 'Chờ chuyển khoản')->exists())->toBeTrue();
});

it('updates conversation tags scoped to a contact', function () use ($makeContactWithConversation): void {
    $contact = $makeContactWithConversation();
    $conversation = $contact->conversations()->first();
    $tag = OmnichatTag::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.livechat.contacts.conversation-tags.update', $contact), [
            'conversation_id' => $conversation->id,
            'tag_ids' => [$tag->id],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'tags')
        ->assertJsonPath('tags.0.id', $tag->id);

    expect($conversation->fresh()->tags->pluck('id'))->toContain($tag->id);
});

it('rejects conversation tag updates for a conversation outside the contact', function () use ($makeContactWithConversation): void {
    $contact = $makeContactWithConversation();
    $otherContact = $makeContactWithConversation();
    $otherConversation = $otherContact->conversations()->first();

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.livechat.contacts.conversation-tags.update', $contact), [
            'conversation_id' => $otherConversation->id,
            'tag_ids' => [],
        ])
        ->assertNotFound();
});
