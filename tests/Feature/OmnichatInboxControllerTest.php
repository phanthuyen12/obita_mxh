<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
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
});

it('requires authentication to view the omnichat inbox', function (): void {
    $this->get(route('app.omnichat.index'))
        ->assertRedirect(route('login'));
});

it('shows the omnichat inbox', function (): void {
    $channel = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'AE Trading Page',
    ]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.omnichat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('omnichat/Inbox')
            ->has('conversations.data', 0)
            ->has('connectedChannels', 1)
            ->where('connectedChannels.0.id', $channel->id)
            ->where('connectedChannels.0.name', 'AE Trading Page')
            ->where('connectedChannels.0.provider', 'facebook')
            ->where('selectedChannelIds.0', $channel->id)
            ->where('focusedChannelId', $channel->id)
            ->where('selectedConversation', null)
            ->where('messages', null));
    expect($this->user->fresh()->current_omnichat_social_account_id)->toBe($channel->id);
});

it('does not expose wordpress publishing accounts as omnichat channels', function (): void {
    $facebookChannel = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Facebook Inbox',
    ]);
    $wordpressAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::WordPress,
        'display_name' => 'WordPress Publishing',
    ]);

    $this->user->omnichatViewSocialAccounts()->sync([$wordpressAccount->id]);

    $this->actingAs($this->user->fresh())
        ->get(route('app.omnichat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('omnichat/Inbox')
            ->has('connectedChannels', 1)
            ->where('connectedChannels.0.id', $facebookChannel->id)
            ->where('selectedChannelIds', [$facebookChannel->id])
            ->where('focusedChannelId', $facebookChannel->id)
        );

    expect($this->user->omnichatViewSocialAccounts()->pluck('social_accounts.id')->all())
        ->toBe([$facebookChannel->id]);
});

it('stores multiple selected pages and shows their conversations', function (): void {
    $firstChannel = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $selectedChannel = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $firstConversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $firstChannel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id])->id,
    ]);
    $selectedConversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $selectedChannel->id,
        'contact_id' => OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id])->id,
    ]);

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.view.update'), [
            'channel_ids' => [$firstChannel->id, $selectedChannel->id],
        ])
        ->assertOk()
        ->assertJsonPath('selected_channel_ids.0', $firstChannel->id)
        ->assertJsonPath('selected_channel_ids.1', $selectedChannel->id);

    expect($this->user->fresh()->current_omnichat_social_account_id)->toBe($firstChannel->id);

    $this->get(route('app.omnichat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedChannelIds', [$firstChannel->id, $selectedChannel->id])
            ->has('conversations.data', 2));

    expect($firstConversation->id)->not->toBe($selectedConversation->id);
});

it('prevents selecting a page from another workspace', function (): void {
    $foreignChannel = SocialAccount::factory()->facebook()->create();

    $this->actingAs($this->user->fresh())
        ->putJson(route('app.omnichat.view.update'), ['channel_ids' => [$foreignChannel->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel_ids.0');

    expect($this->user->fresh()->current_omnichat_social_account_id)->toBeNull();
});

it('can clear the selected conversation for mobile navigation', function (): void {
    $this->actingAs($this->user->fresh())
        ->get(route('app.omnichat.index', ['conversation' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedConversation', null)
            ->where('messages', null));
});
