<?php

declare(strict_types=1);

use App\Enums\Folder\Permission;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->admin = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->admin->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->admin->id,
    ]);
    $this->workspace->members()->attach($this->admin->id, ['role' => Role::Admin->value]);
    $this->admin->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);
});

test('admin can view the team settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('app.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/workspace/Teams')
            ->has('teams')
            ->missing('members')
        );
});

test('admin can search workspace members with server side pagination', function () {
    $matchingMember = User::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Nguyen Marketing',
        'email' => 'marketing@example.com',
    ]);
    $this->workspace->members()->attach($matchingMember->id, ['role' => Role::Member->value]);

    $this->actingAs($this->admin)
        ->getJson(route('app.teams.members', ['search' => 'Marketing']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingMember->id)
        ->assertJsonPath('per_page', 30);
});

test('admin can create a team with workspace members', function () {
    $member = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    $this->actingAs($this->admin)->post(route('app.teams.store'), [
        'name' => 'Marketing',
        'description' => 'Marketing team',
        'is_active' => true,
        'user_ids' => [$member->id],
    ])->assertRedirect();

    $team = Team::query()->where('name', 'Marketing')->firstOrFail();

    expect($team->workspace_id)->toBe($this->workspace->id)
        ->and($team->users()->pluck('users.id')->all())->toBe([$member->id]);
});

test('team cannot include a user outside the workspace', function () {
    $outsider = User::factory()->create(['account_id' => $this->account->id]);

    $this->actingAs($this->admin)->post(route('app.teams.store'), [
        'name' => 'Invalid',
        'user_ids' => [$outsider->id],
    ])->assertSessionHasErrors('user_ids.0');
});

test('deleting a team removes folder permissions but not users', function () {
    $team = Team::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'CEO',
        'created_by' => $this->admin->id,
    ]);
    $team->users()->attach($this->admin->id);
    $folder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    FolderPermission::query()->create([
        'folder_id' => $folder->id,
        'team_id' => $team->id,
        'permission' => Permission::View,
        'assigned_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('app.teams.destroy', $team))
        ->assertRedirect();

    expect(Team::query()->find($team->id))->toBeNull()
        ->and(FolderPermission::query()->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(User::query()->find($this->admin->id))->not->toBeNull();
});
