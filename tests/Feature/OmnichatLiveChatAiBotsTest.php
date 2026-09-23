<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\AiBot;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
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
    ]);
});

it('lists workspace ai bots without leaking the api key', function (): void {
    AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Bot Tư Vấn',
        'is_default' => true,
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.ai-bots.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bot Tư Vấn')
        ->assertJsonPath('data.0.key_set', true)
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonMissing(['dify_api_key']);
});

it('creates an ai bot for admins', function (): void {
    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.livechat.ai-bots.store'), [
            'name' => 'Bot Chốt Đơn',
            'dify_api_key' => 'app-secret',
        ])
        ->assertCreated()
        ->assertJsonPath('bot.name', 'Bot Chốt Đơn')
        ->assertJsonPath('bot.key_set', true)
        ->assertJsonMissing(['dify_api_key']);

    expect(AiBot::query()->where('workspace_id', $this->workspace->id)->where('name', 'Bot Chốt Đơn')->exists())->toBeTrue();
});

it('keeps a single default bot per workspace', function (): void {
    $first = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_default' => true,
    ]);
    $second = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.ai-bots.update', $second), [
            'is_default' => true,
        ])
        ->assertOk()
        ->assertJsonPath('bot.is_default', true);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('keeps the existing api key when updating without one', function (): void {
    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'dify_api_key' => 'original-key',
    ]);

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.ai-bots.update', $bot), [
            'name' => 'Renamed Bot',
        ])
        ->assertOk()
        ->assertJsonPath('bot.name', 'Renamed Bot');

    expect($bot->fresh()->dify_api_key)->toBe('original-key');
});

it('deletes an ai bot', function (): void {
    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->owner->fresh())
        ->deleteJson(route('app.omnichat.livechat.ai-bots.destroy', $bot))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect($bot->fresh())->toBeNull();
});

it('forbids members without team management rights from managing ai bots', function (): void {
    $member = User::factory()->create();
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->postJson(route('app.omnichat.livechat.ai-bots.store'), [
            'name' => 'Nope',
            'dify_api_key' => 'app-x',
        ])
        ->assertForbidden();

    $this->actingAs($member->fresh())
        ->deleteJson(route('app.omnichat.livechat.ai-bots.destroy', $bot))
        ->assertForbidden();
});

it('shows a detailed customer profile with every conversation', function (): void {
    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'khach@example.com',
    ]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'contact_id' => $contact->id,
        'last_message_preview' => 'Chào shop',
    ]);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.show', $contact))
        ->assertOk()
        ->assertJsonPath('contact.id', $contact->id)
        ->assertJsonPath('contact.email', 'khach@example.com')
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conversation->id)
        ->assertJsonPath('conversations.0.last_message_preview', 'Chào shop');
});

it('hides contacts of other workspaces', function (): void {
    $foreignContact = OmnichatContact::factory()->create();

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.contacts.show', $foreignContact))
        ->assertNotFound();
});

it('toggles conversation ai as json for axios calls', function (): void {
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $this->channel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id])->id,
    ]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.conversations.ai-toggle', $conversation))
        ->assertOk()
        ->assertJsonPath('ai_paused', true);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.conversations.ai-toggle', $conversation))
        ->assertOk()
        ->assertJsonPath('ai_paused', false);
});

it('falls back to the workspace default bot when the channel has no key', function (): void {
    $fallback = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_default' => true,
    ]);

    $default = AiBot::defaultFor($this->workspace->id);

    expect($default)->not->toBeNull()
        ->and($default->id)->toBe($fallback->id);
});

it('lists the ai state of every livechat channel', function (): void {
    $this->channel->forceFill([
        'meta' => ['ai_care' => ['enabled' => true, 'bot_id' => null]],
    ])->save();

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.omnichat.livechat.channel-ai.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->channel->id)
        ->assertJsonPath('data.0.ai_enabled', true);
});

it('lets members toggle ai per channel without management rights', function (): void {
    $member = User::factory()->create();
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value, 'can_omnichat' => true]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->putJson(route('app.omnichat.livechat.channel-ai.update', $this->channel->id), [
            'ai_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ai_enabled', true);

    expect(data_get($this->channel->fresh()->meta, 'ai_care.enabled'))->toBeTrue();
});

it('forbids members from assigning a bot to a channel', function (): void {
    $member = User::factory()->create();
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value, 'can_omnichat' => true]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->putJson(route('app.omnichat.livechat.channel-ai.update', $this->channel->id), [
            'ai_enabled' => true,
            'bot_id' => $bot->id,
        ])
        ->assertForbidden();
});

it('lets admins assign a bot to a channel and sales toggles keep the assignment', function (): void {
    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.channel-ai.update', $this->channel->id), [
            'ai_enabled' => true,
            'bot_id' => $bot->id,
        ])
        ->assertOk()
        ->assertJsonPath('bot_id', $bot->id);

    // A member switching AI off must not wipe the admin's bot assignment.
    $member = User::factory()->create();
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value, 'can_omnichat' => true]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->putJson(route('app.omnichat.livechat.channel-ai.update', $this->channel->id), [
            'ai_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('ai_enabled', false);

    expect(data_get($this->channel->fresh()->meta, 'ai_care.bot_id'))->toBe($bot->id)
        ->and(data_get($this->channel->fresh()->meta, 'ai_care.enabled'))->toBeFalse();
});

it('rejects assigning a bot from another workspace', function (): void {
    $foreignBot = AiBot::factory()->create();

    $this->actingAs($this->owner->fresh())
        ->putJson(route('app.omnichat.livechat.channel-ai.update', $this->channel->id), [
            'ai_enabled' => true,
            'bot_id' => $foreignBot->id,
        ])
        ->assertInvalid('bot_id');
});

it('accepts telegram images up to 10 megabytes', function (): void {
    $channel = \App\Models\OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => \App\Enums\Omnichat\ChannelProvider::Telegram,
    ]);
    $conversation = \App\Models\OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $channel->id,
        'contact_id' => \App\Models\OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id])->id,
    ]);

    // A 3 MB "photo" — previously rejected by the blanket 1 MB cap.
    $file = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg')->size(3072);

    \Illuminate\Support\Facades\Http::fake([
        '*/bot*/sendPhoto' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => ['message_id' => 42]]),
    ]);

    $response = $this->actingAs($this->owner->fresh())
        ->post(route('app.omnichat.messages.store', $conversation), [
            'body' => 'Ảnh nè',
            'mode' => 'reply',
            'client_id' => (string) \Illuminate\Support\Str::uuid(),
            'image' => $file,
        ]);

    $response->assertSessionDoesntHaveErrors('image');
});
