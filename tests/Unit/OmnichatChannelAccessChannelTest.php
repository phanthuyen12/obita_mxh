<?php

declare(strict_types=1);

use App\Broadcasting\OmnichatChannelAccessChannel;
use App\Enums\Omnichat\ChannelProvider;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatChannel;
use App\Models\User;
use App\Models\Workspace;

test('omnichat channel allows a workspace member to join a telegram bot channel', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $channel = OmnichatChannel::factory()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
    ]);

    expect((new OmnichatChannelAccessChannel)->join($user->fresh(), $channel->id))->toBeTrue();
});

test('omnichat channel still allows a workspace member to join a website channel', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $channel = OmnichatChannel::factory()->website()->create([
        'workspace_id' => $workspace->id,
    ]);

    expect((new OmnichatChannelAccessChannel)->join($user->fresh(), $channel->id))->toBeTrue();
});

test('omnichat channel denies a telegram bot the user is not in the workspace of', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $foreignChannel = OmnichatChannel::factory()->create([
        'provider' => ChannelProvider::Telegram,
    ]);

    expect((new OmnichatChannelAccessChannel)->join($user->fresh(), $foreignChannel->id))->toBeFalse();
});
