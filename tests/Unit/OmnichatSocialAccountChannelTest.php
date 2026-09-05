<?php

declare(strict_types=1);

use App\Broadcasting\OmnichatSocialAccountChannel;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

test('omnichat channel allows the selected page for a workspace member', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->omnichatViewSocialAccounts()->attach($account);

    expect((new OmnichatSocialAccountChannel)->join($user->fresh(), $account))->toBeTrue();
});

test('omnichat channel denies a page the user is not currently viewing', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $selectedAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $otherAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->omnichatViewSocialAccounts()->attach($selectedAccount);

    expect((new OmnichatSocialAccountChannel)->join($user->fresh(), $otherAccount))->toBeFalse();
});
