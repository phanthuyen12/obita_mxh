<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\SocialAccountGroup;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, [
        'role' => Role::Admin->value,
    ]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('accounts page exposes page groups with their members', function () {
    $socialAccounts = SocialAccount::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $group = SocialAccountGroup::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Khách hàng miền Nam',
    ]);
    $group->socialAccounts()->attach($socialAccounts);

    $this->actingAs($this->user)
        ->get(route('app.accounts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/Index')
            ->has('pageGroups.data', 1)
            ->where('pageGroups.data.0.id', $group->id)
            ->where('pageGroups.data.0.name', 'Khách hàng miền Nam')
            ->where('pageGroups.data.0.social_accounts_count', 2)
            ->has('pageGroups.data.0.social_accounts', 2)
        );
});

test('accounts page paginates and filters large page collections on the server', function () {
    SocialAccount::factory()->count(30)->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.accounts', ['ownership' => 'owned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accountPage.data', 24)
            ->where('accountPage.total', 30)
            ->where('accountPage.per_page', 24)
            ->where('accountFilters.ownership', 'owned')
        );
});

test('account browser API limits and searches page results', function () {
    SocialAccount::factory()->count(60)->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->user->id,
    ]);
    $match = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->user->id,
        'display_name' => 'Unique Southern Brand',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.accounts.browser', ['per_page' => 500]))
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50);

    $this->actingAs($this->user)
        ->getJson(route('app.accounts.browser', ['search' => 'Southern Brand']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('workspace admin can create a page group', function () {
    $socialAccounts = SocialAccount::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('app.account-groups.store'), [
            'name' => 'Thương hiệu F&B',
            'social_account_ids' => $socialAccounts->pluck('id')->all(),
        ])
        ->assertRedirect();

    $group = SocialAccountGroup::query()->sole();

    expect($group->name)->toBe('Thương hiệu F&B')
        ->and($group->workspace_id)->toBe($this->workspace->id)
        ->and($group->socialAccounts()->pluck('social_accounts.id')->all())
        ->toEqualCanonicalizing($socialAccounts->pluck('id')->all());
});

test('workspace admin can create an empty page group', function () {
    $this->actingAs($this->user)
        ->post(route('app.account-groups.store'), [
            'name' => 'Nhóm chưa có Page',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $group = SocialAccountGroup::query()->sole();

    expect($group->name)->toBe('Nhóm chưa có Page')
        ->and($group->socialAccounts()->count())->toBe(0);
});

test('workspace admin can update and delete a page group without deleting pages', function () {
    $firstAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $secondAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $group = SocialAccountGroup::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $group->socialAccounts()->attach($firstAccount);

    $this->actingAs($this->user)
        ->put(route('app.account-groups.update', $group), [
            'name' => 'Nhóm đã đổi tên',
            'social_account_ids' => [$secondAccount->id],
        ])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('Nhóm đã đổi tên')
        ->and($group->socialAccounts()->pluck('social_accounts.id')->all())
        ->toBe([$secondAccount->id]);

    $this->actingAs($this->user)
        ->delete(route('app.account-groups.destroy', $group))
        ->assertRedirect();

    $this->assertDatabaseMissing('social_account_groups', ['id' => $group->id]);
    $this->assertDatabaseHas('social_accounts', ['id' => $firstAccount->id]);
    $this->assertDatabaseHas('social_accounts', ['id' => $secondAccount->id]);
});

test('page groups reject social accounts from another workspace', function () {
    $foreignAccount = SocialAccount::factory()->create();

    $this->actingAs($this->user)
        ->post(route('app.account-groups.store'), [
            'name' => 'Không hợp lệ',
            'social_account_ids' => [$foreignAccount->id],
        ])
        ->assertSessionHasErrors('social_account_ids.0');

    expect(SocialAccountGroup::query()->count())->toBe(0);
});

test('workspace member cannot manage page groups', function () {
    $member = User::factory()->create([
        'account_id' => $this->user->account_id,
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
    ]);

    $this->actingAs($member)
        ->post(route('app.account-groups.store'), [
            'name' => 'Không được phép',
            'social_account_ids' => [],
        ])
        ->assertForbidden();
});
