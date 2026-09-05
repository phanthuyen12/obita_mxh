<?php

declare(strict_types=1);

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatChannel;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

function websiteChatAdmin(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $user->account_id, 'user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

it('lets a workspace admin create and view a website chat channel', function (): void {
    [$user, $workspace] = websiteChatAdmin();

    $this->actingAs($user)->post(route('app.omnichat.website-chat.store'), [
        'name' => 'Website King Coffee',
        'authorized_origins' => ['https://shop.example.com/'],
        'welcome_message' => 'Xin chào!',
        'offline_message' => 'Vui lòng để lại lời nhắn.',
        'primary_color' => '#2563eb',
        'position' => 'right',
        'privacy_url' => 'https://shop.example.com/privacy',
    ])->assertRedirect();

    $channel = OmnichatChannel::query()->sole();
    expect($channel->workspace_id)->toBe($workspace->id)
        ->and($channel->provider)->toBe(ChannelProvider::Website)
        ->and($channel->access_token)->toStartWith('wc_pk_')
        ->and($channel->settings['authorized_origins'])->toBe(['https://shop.example.com']);

    $this->actingAs($user)->get(route('app.omnichat.website-chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('omnichat/WebsiteChat')
            ->where('channels.0.id', $channel->id)
            ->where('channels.0.public_key', $channel->access_token));
});

it('prevents an admin from changing a website channel in another workspace', function (): void {
    [$user] = websiteChatAdmin();
    $otherChannel = OmnichatChannel::factory()->website()->create();

    $this->actingAs($user)->put(route('app.omnichat.website-chat.update', $otherChannel), [
        'name' => 'Không hợp lệ',
        'authorized_origins' => ['https://example.com'],
        'welcome_message' => 'Xin chào!',
        'offline_message' => 'Ngoài giờ.',
        'primary_color' => '#2563EB',
        'position' => 'right',
        'privacy_url' => null,
    ])->assertNotFound();
});

it('lists website channels in connections and shares granular Omnichat access', function (): void {
    [$admin, $workspace] = websiteChatAdmin();
    $member = User::factory()->create(['account_id' => $workspace->account_id]);
    $workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
        'can_omnichat' => true,
        'can_content' => true,
    ]);
    $member->update(['current_workspace_id' => $workspace->id]);
    $channel = OmnichatChannel::factory()->website()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Website hỗ trợ',
    ]);

    $this->actingAs($admin)
        ->get(route('app.accounts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('websiteChatChannels.0.id', $channel->id)
            ->where('websiteChatChannels.0.name', 'Website hỗ trợ')
            ->where('websiteChatChannels.0.public_key', $channel->access_token)
            ->where('websiteChatChannels.0.settings', $channel->settings));

    $this->actingAs($admin)->put(route('app.omnichat-channels.access.update', $channel), [
        'user_ids' => [$member->id],
        'permissions' => [
            $member->id => [
                'can_view_omnichat' => true,
                'can_reply_omnichat' => false,
                'can_assign_conversations' => false,
            ],
        ],
    ])->assertRedirect();

    expect($member->fresh()->can('viewOmnichat', $channel))->toBeTrue()
        ->and($member->fresh()->can('replyOmnichat', $channel))->toBeFalse();
});
