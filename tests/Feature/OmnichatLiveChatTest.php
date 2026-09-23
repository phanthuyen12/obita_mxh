<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
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
        'display_name' => 'Sales Page',
    ]);
});

$makeConversation = function (array $attributes = []): OmnichatConversation {
    return OmnichatConversation::factory()->create(array_merge([
        'workspace_id' => test()->workspace->id,
        'social_account_id' => test()->channel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => test()->workspace->id])->id,
    ], $attributes));
};

it('renders the livechat inertia page', function (): void {
    $this->actingAs($this->user->fresh())
        ->get(route('app.omnichat.livechat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('omnichat/LiveChat'));
});

it('requires authentication to view the livechat page', function (): void {
    $this->get(route('app.omnichat.livechat.index'))
        ->assertRedirect(route('login'));
});

it('lists accessible conversations as json with pagination meta', function () use ($makeConversation): void {
    $makeConversation();
    $makeConversation();

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.has_next_page', false)
        ->assertJsonStructure([
            'data' => [['id', 'contact' => ['display_name'], 'channel' => ['provider'], 'unread_count', 'labels']],
            'meta' => ['current_page', 'last_page', 'has_next_page'],
        ]);
});

it('uses the configured default page size for the conversation list', function () use ($makeConversation): void {
    config(['app.pagination.default' => 3]);

    foreach (range(1, 5) as $ignored) {
        $makeConversation();
    }

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.has_next_page', true);
});

it('does not list conversations from channels the user cannot access', function () use ($makeConversation): void {
    $accessible = $makeConversation();

    $foreignChannel = SocialAccount::factory()->facebook()->create();
    OmnichatConversation::factory()->create([
        'workspace_id' => $foreignChannel->workspace_id,
        'social_account_id' => $foreignChannel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => $foreignChannel->workspace_id])->id,
    ]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $accessible->id);
});

it('filters conversations by search term', function () use ($makeConversation): void {
    $matching = $makeConversation([
        'contact_id' => OmnichatContact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'display_name' => 'Nguyen Van A',
        ])->id,
    ]);
    $makeConversation([
        'contact_id' => OmnichatContact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'display_name' => 'Someone Else',
        ])->id,
    ]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.index', ['search' => 'Nguyen']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('filters conversations by label', function () use ($makeConversation): void {
    $tag = OmnichatTag::factory()->create(['workspace_id' => $this->workspace->id]);
    $tagged = $makeConversation();
    $tagged->tags()->attach($tag->id);
    $makeConversation();

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.index', ['label' => $tag->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tagged->id);
});

it('shows a conversation with its paginated messages', function () use ($makeConversation): void {
    $conversation = $makeConversation();
    OmnichatMessage::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
    ]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.id', $conversation->id)
        ->assertJsonCount(2, 'messages.data')
        ->assertJsonStructure([
            'conversation' => ['id', 'contact' => ['display_name'], 'channel' => ['provider']],
            'messages' => ['data' => [['id', 'body', 'direction']], 'meta' => ['current_page', 'last_page', 'has_next_page']],
        ]);
});

it('forbids viewing a conversation from another workspace', function () use ($makeConversation): void {
    $foreignChannel = SocialAccount::factory()->facebook()->create();
    $foreignConversation = OmnichatConversation::factory()->create([
        'workspace_id' => $foreignChannel->workspace_id,
        'social_account_id' => $foreignChannel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => $foreignChannel->workspace_id])->id,
    ]);

    $this->actingAs($this->user->fresh())
        ->getJson(route('app.omnichat.livechat.conversations.show', $foreignConversation))
        ->assertForbidden();
});
