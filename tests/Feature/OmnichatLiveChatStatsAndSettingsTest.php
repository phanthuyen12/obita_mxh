<?php

declare(strict_types=1);

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatChannel;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    config(['trypost.self_hosted' => true]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->channel = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Sales Page',
    ]);
});

it('returns real livechat statistics for the today period', function (): void {
    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'contact_id' => $contact->id,
    ]);

    OmnichatMessage::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.analytics'))
        ->assertOk()
        ->assertJsonPath('period', 'today')
        ->assertJsonPath('messages', 3)
        ->assertJsonPath('conversations', 1)
        ->assertJsonPath('new_customers', 1)
        ->assertJsonStructure([
            'period', 'from', 'to', 'messages', 'conversations', 'new_customers',
            'avg_response_display', 'ai_handled_rate', 'ai_enabled', 'top_channels',
        ]);
});

it('supports the week and month periods', function (string $period): void {
    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.analytics', ['period' => $period]))
        ->assertOk()
        ->assertJsonPath('period', $period);
})->with(['week', 'month']);

it('rejects an invalid analytics period', function (): void {
    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.analytics', ['period' => 'year']))
        ->assertInvalid('period');
});

it('lists the workspace channels inside top_channels when they have activity', function (): void {
    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'contact_id' => $contact->id,
    ]);
    OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'conversation_id' => $conversation->id,
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.analytics'))
        ->assertOk()
        ->assertJsonCount(1, 'top_channels')
        ->assertJsonPath('top_channels.0.name', 'Sales Page');
});

it('includes omnichat channels such as telegram bots in top_channels', function (): void {
    $telegramChannel = OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => ChannelProvider::Telegram,
        'name' => 'AnnalyTrader_Bot',
        'settings' => ['ai_care' => ['enabled' => true, 'dify_api_key' => 'app-x']],
    ]);

    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $telegramChannel->id,
        'contact_id' => $contact->id,
    ]);
    OmnichatMessage::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $telegramChannel->id,
        'conversation_id' => $conversation->id,
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.analytics'))
        ->assertOk()
        ->assertJsonCount(1, 'top_channels')
        ->assertJsonPath('top_channels.0.name', 'AnnalyTrader_Bot')
        ->assertJsonPath('top_channels.0.platform', 'telegram')
        ->assertJsonPath('ai_enabled', true);
});

it('shows the current ai settings without leaking the api key', function (): void {
    $channel = OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'settings' => ['ai_care' => ['enabled' => true, 'dify_api_key' => 'secret-key']],
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.settings.show'))
        ->assertOk()
        ->assertJsonPath('ai_enabled', true)
        ->assertJsonPath('api_key_set', true)
        ->assertJsonMissing(['dify_api_key' => 'secret-key']);

    expect($channel->settings['ai_care']['dify_api_key'])->toBe('secret-key');
});

it('batch-updates ai settings on every connected channel', function (): void {
    $telegramChannel = OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.settings.update'), [
            'ai_enabled' => true,
            'dify_api_key' => 'app-abc123',
        ])
        ->assertOk()
        ->assertJsonPath('ai_enabled', true)
        ->assertJsonPath('api_key_set', true);

    expect($this->channel->fresh()->meta['ai_care'])->toMatchArray([
        'enabled' => true,
        'dify_api_key' => 'app-abc123',
    ])
        ->and($telegramChannel->fresh()->settings['ai_care'])->toMatchArray([
            'enabled' => true,
            'dify_api_key' => 'app-abc123',
        ]);
});

it('keeps the existing api key when none is provided', function (): void {
    $this->channel->forceFill([
        'meta' => ['ai_care' => ['enabled' => true, 'dify_api_key' => 'existing-key']],
    ])->save();

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.settings.update'), [
            'ai_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('ai_enabled', false)
        ->assertJsonPath('api_key_set', true);

    expect($this->channel->fresh()->meta['ai_care'])->toMatchArray([
        'enabled' => false,
        'dify_api_key' => 'existing-key',
    ]);
});

it('forbids members without team management rights from updating ai settings', function (): void {
    $member = User::factory()->create();
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->putJson(route('app.omnichat.livechat.settings.update'), [
            'ai_enabled' => true,
        ])
        ->assertForbidden();
});
