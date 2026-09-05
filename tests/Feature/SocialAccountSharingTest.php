<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->member = User::factory()->create([
        'account_id' => $this->owner->account_id,
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->member->id, ['role' => Role::Member->value]);
});

test('member sees an admin shared page without connection management permissions', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
        'platform' => Platform::Facebook,
    ]);
    $account->sharedUsers()->attach($this->member->id, ['granted_by_user_id' => $this->owner->id]);

    $this->actingAs($this->member)
        ->get(route('app.accounts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('connectedAccounts.0.id', $account->id)
            ->where('connectedAccounts.0.ownership_type', 'shared')
            ->where('connectedAccounts.0.can_disconnect', false)
            ->where('connectedAccounts.0.can_share', false));
});

test('accounts index resolves page permissions without lazy loading workspace', function () {
    Model::preventLazyLoading();

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.accounts'))
        ->assertOk();
});

test('checking page permission does not lazy load the workspace relationship', function () {
    Model::preventLazyLoading();

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);

    expect($account->userHasAccess($this->owner, 'can_publish_posts'))->toBeTrue();
});

test('member cannot disconnect or toggle an admin shared page', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);
    $account->sharedUsers()->attach($this->member->id, ['granted_by_user_id' => $this->owner->id]);

    $this->actingAs($this->member)
        ->delete(route('app.accounts.disconnect', $account))
        ->assertForbidden();
    $this->actingAs($this->member)
        ->put(route('app.accounts.toggle', $account))
        ->assertForbidden();

    $this->assertModelExists($account);
});

test('member can disconnect a page they connected themselves', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->member->id,
    ]);

    $this->actingAs($this->member)
        ->delete(route('app.accounts.disconnect', $account))
        ->assertRedirect();

    $this->assertModelMissing($account);
});

test('admin can share and revoke a page while a member cannot change access', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->put(route('app.accounts.access.update', $account), ['user_ids' => [$this->member->id]])
        ->assertRedirect();

    expect($account->sharedUsers()->whereKey($this->member->id)->exists())->toBeTrue();

    $this->actingAs($this->member)
        ->put(route('app.accounts.access.update', $account), ['user_ids' => []])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->put(route('app.accounts.access.update', $account), ['user_ids' => []])
        ->assertRedirect();

    expect($account->sharedUsers()->whereKey($this->member->id)->exists())->toBeFalse();
});
