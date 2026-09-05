<?php

declare(strict_types=1);

use App\Enums\Folder\Permission;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Folder;
use App\Models\FolderPermission;
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

function folderMember(Account $account, Workspace $workspace): User
{
    $member = User::factory()->create(['account_id' => $account->id]);
    $workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
        'can_content' => true,
    ]);
    $member->update(['current_workspace_id' => $workspace->id]);

    return $member;
}

test('workspace admin can create a master folder', function () {
    $response = $this->actingAs($this->admin)->postJson(route('app.folders.store'), [
        'name' => 'Marketing',
        'type' => 'master',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Marketing')
        ->assertJsonPath('data.type', 'master');

    expect(Folder::query()->first())
        ->workspace_id->toBe($this->workspace->id)
        ->parent_id->toBeNull()
        ->master_folder_id->toBeNull();
});

test('workspace admin can open the master folder management page', function () {
    Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
        'name' => 'Marketing',
    ]);

    $this->actingAs($this->admin)
        ->get(route('app.folders.manage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('folders/Index')
            ->has('folders', 1)
            ->where('folders.0.name', 'Marketing')
            ->has('permissionOptions', count(Permission::cases()))
            ->missing('users')
            ->missing('teams')
        );
});

test('admin can search permission subjects with server side pagination', function () {
    $member = User::factory()->create([
        'account_id' => $this->account->id,
        'name' => 'Marketing Specialist',
        'email' => 'specialist@example.com',
    ]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    $this->actingAs($this->admin)
        ->getJson(route('app.folders.subjects', ['type' => 'user', 'search' => 'Specialist']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id)
        ->assertJsonPath('per_page', 30);
});

test('member can open folder sharing page without seeing private folders of another member', function () {
    $member = folderMember($this->account, $this->workspace);
    $otherMember = folderMember($this->account, $this->workspace);
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    $privateFolder = Folder::factory()->personal($master)->create([
        'created_by' => $otherMember->id,
        'owner_user_id' => $otherMember->id,
        'name' => 'Private campaign',
    ]);

    $this->actingAs($member)
        ->get(route('app.folders.manage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('folders/Index')
            ->where('folders', [])
        );

    expect($privateFolder->userHasPermission($member, Permission::View))->toBeFalse();
});

test('owner can share a personal folder with one employee', function () {
    $owner = folderMember($this->account, $this->workspace);
    $recipient = folderMember($this->account, $this->workspace);
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    $folder = Folder::factory()->personal($master)->create([
        'created_by' => $owner->id,
        'owner_user_id' => $owner->id,
        'name' => 'Marketing',
    ]);

    $this->actingAs($owner)->putJson(route('app.folders.permissions.update', $folder), [
        'permissions' => [[
            'user_id' => $recipient->id,
            'team_id' => null,
            'permission' => Permission::View->value,
        ]],
    ])->assertOk();

    expect($folder->userHasPermission($owner, Permission::View))->toBeTrue()
        ->and($folder->userHasPermission($recipient, Permission::View))->toBeTrue();
});

test('owner can share a personal folder with the whole workspace', function () {
    $owner = folderMember($this->account, $this->workspace);
    $recipient = folderMember($this->account, $this->workspace);
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    $folder = Folder::factory()->personal($master)->create([
        'created_by' => $owner->id,
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)->putJson(route('app.folders.update', $folder), [
        'is_shared_with_workspace' => true,
    ])->assertOk();

    expect($folder->fresh()->userHasPermission($recipient, Permission::View))->toBeTrue()
        ->and($folder->fresh()->userHasPermission($recipient, Permission::UploadMedia))->toBeFalse();
});

test('member cannot create a master folder', function () {
    $member = folderMember($this->account, $this->workspace);

    $this->actingAs($member)->postJson(route('app.folders.store'), [
        'name' => 'CEO',
        'type' => 'master',
    ])->assertForbidden();
});

test('member with inherited permission can create nested personal folders', function () {
    $member = folderMember($this->account, $this->workspace);
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    FolderPermission::query()->create([
        'folder_id' => $master->id,
        'user_id' => $member->id,
        'permission' => Permission::CreateFolder,
        'assigned_by' => $this->admin->id,
    ]);

    $firstResponse = $this->actingAs($member)->postJson(route('app.folders.store'), [
        'name' => 'Facebook',
        'type' => 'personal',
        'parent_id' => $master->id,
    ])->assertCreated();

    $first = Folder::query()->findOrFail($firstResponse->json('data.id'));

    $secondResponse = $this->actingAs($member)->postJson(route('app.folders.store'), [
        'name' => 'Campaign August',
        'type' => 'personal',
        'parent_id' => $first->id,
    ])->assertCreated();

    $second = Folder::query()->findOrFail($secondResponse->json('data.id'));

    expect($first->owner_user_id)->toBe($member->id)
        ->and($first->master_folder_id)->toBe($master->id)
        ->and($second->parent_id)->toBe($first->id)
        ->and($second->master_folder_id)->toBe($master->id);
});

test('personal folder payload shows creator email when legacy owner is missing', function () {
    $member = folderMember($this->account, $this->workspace);
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    $folder = Folder::factory()->personal($master)->create([
        'created_by' => $member->id,
        'owner_user_id' => null,
        'name' => 'Chiến dịch tháng 8',
        'is_shared_with_workspace' => true,
    ]);

    $this->actingAs($member)
        ->getJson(route('app.folders.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $folder->id)
        ->assertJsonPath('data.0.owner_email', $member->email)
        ->assertJsonPath('data.0.display_name', "Chiến dịch tháng 8 · {$member->email}");
});

test('folder parent must belong to the current workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherMaster = Folder::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'created_by' => $otherWorkspace->user_id,
    ]);

    $this->actingAs($this->admin)->postJson(route('app.folders.store'), [
        'name' => 'Invalid child',
        'type' => 'personal',
        'parent_id' => $otherMaster->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('folder cannot be moved into its descendant', function () {
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    $parent = Folder::factory()->personal($master)->create([
        'created_by' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
    ]);
    $child = Folder::factory()->personal($parent)->create([
        'created_by' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->putJson(route('app.folders.update', $parent), [
        'parent_id' => $child->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('cross workspace folder is hidden as not found', function () {
    $otherWorkspace = Workspace::factory()->create();
    $folder = Folder::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'created_by' => $otherWorkspace->user_id,
    ]);

    $this->actingAs($this->admin)->putJson(route('app.folders.update', $folder), [
        'name' => 'Leaked',
    ])->assertNotFound();
});

test('folder with children cannot be deleted', function () {
    $master = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
    Folder::factory()->personal($master)->create([
        'created_by' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson(route('app.folders.destroy', $master))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder');
});

test('one workspace user can receive multiple permissions on the same folder', function () {
    $member = folderMember($this->account, $this->workspace);
    $folder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('app.folders.permissions.update', $folder), [
            'permissions' => [
                ['user_id' => $member->id, 'team_id' => null, 'permission' => Permission::View->value],
                ['user_id' => $member->id, 'team_id' => null, 'permission' => Permission::CreateFolder->value],
                ['user_id' => $member->id, 'team_id' => null, 'permission' => Permission::UploadMedia->value],
                ['user_id' => $member->id, 'team_id' => null, 'permission' => Permission::EditMedia->value],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(4, 'data');

    expect($folder->permissions()->where('user_id', $member->id)->pluck('permission')->all())
        ->toHaveCount(4)
        ->toContain(
            Permission::View,
            Permission::CreateFolder,
            Permission::UploadMedia,
            Permission::EditMedia,
        );
});
