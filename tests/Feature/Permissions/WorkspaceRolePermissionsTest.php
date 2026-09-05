<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->viewer = User::factory()->create([
        'account_id' => $this->owner->account_id,
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->viewer->id, ['role' => Role::Viewer->value]);

    $this->member = User::factory()->create([
        'account_id' => $this->owner->account_id,
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->member->id, [
        'role' => Role::Member->value,
        'can_content' => true,
    ]);
    $workflow = $this->workspace->contentWorkflows()->create([
        'name' => 'Default Workflow',
        'is_active' => true,
    ]);
    $workflow->members()->attach($this->member->id, ['can_write' => true]);

    $this->admin = User::factory()->create([
        'account_id' => $this->owner->account_id,
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->admin->id, ['role' => Role::Admin->value]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'content_workflow_id' => $workflow->id,
    ]);
});

test('a viewer cannot delete a post', function () {
    $this->actingAs($this->viewer)
        ->delete(route('app.posts.destroy', $this->post))
        ->assertForbidden();

    $this->assertDatabaseHas('posts', ['id' => $this->post->id]);
});

test('a viewer cannot create an automation', function () {
    $this->actingAs($this->viewer)
        ->post(route('app.automations.store'))
        ->assertForbidden();
});

test('a member can create an automation', function () {
    $this->actingAs($this->member)
        ->post(route('app.automations.store'))
        ->assertRedirect();
});

test('a viewer can comment on a post', function () {
    $this->actingAs($this->viewer)
        ->postJson(route('app.posts.comments.store', $this->post), ['body' => 'Looks good!'])
        ->assertSuccessful();

    $this->assertDatabaseHas('post_comments', [
        'post_id' => $this->post->id,
        'user_id' => $this->viewer->id,
    ]);
});

test('opening a draft post redirects to the editor for every workspace member', function (string $actor) {
    $this->actingAs($this->{$actor})
        ->get(route('app.posts.show', $this->post))
        ->assertRedirect(route('app.posts.edit', $this->post));
})->with(['admin', 'member', 'viewer']);

test('a viewer can open the post editor to review and comment', function () {
    $this->actingAs($this->viewer)
        ->get(route('app.posts.edit', $this->post))
        ->assertOk();
});

test('a viewer cannot save changes to a post', function () {
    $this->actingAs($this->viewer)
        ->put(route('app.posts.update', $this->post), ['status' => Status::Draft->value])
        ->assertForbidden();
});

test('members can open the connections screen while viewers cannot', function (string $actor, bool $allowed) {
    $response = $this->actingAs($this->{$actor})->get(route('app.accounts'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'admin' => ['admin', true],
    'member' => ['member', true],
    'viewer' => ['viewer', false],
]);

test('opening the editor does not create platform rows for a viewer', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->viewer)
        ->get(route('app.posts.edit', $this->post))
        ->assertOk();

    expect($this->post->postPlatforms()->count())->toBe(0);
});

test('opening the editor syncs platform rows for a member', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->admin->id,
        'is_active' => true,
    ]);
    $account->sharedUsers()->attach($this->member->id, ['granted_by_user_id' => $this->admin->id]);

    $this->actingAs($this->member)
        ->get(route('app.posts.edit', $this->post))
        ->assertOk();

    expect($this->post->postPlatforms()->count())->toBe(1);
});

test('a member without accessible accounts is redirected to the calendar when creating a post', function () {
    $this->actingAs($this->member)
        ->post(route('app.posts.store'))
        ->assertRedirect(route('app.calendar'));
});

test('an admin without connected accounts is sent to the accounts screen when creating a post', function () {
    $this->actingAs($this->admin)
        ->post(route('app.posts.store'))
        ->assertRedirect(route('app.accounts'));
});

test('a member cannot open the create workspace form', function () {
    $this->actingAs($this->member)
        ->get(route('app.workspaces.create'))
        ->assertForbidden();
});

test('a member cannot store a workspace on the shared account', function () {
    $this->actingAs($this->member)
        ->post(route('app.workspaces.store'), [
            'name' => 'Sneaky Workspace',
        ])
        ->assertForbidden();

    expect(Workspace::where('name', 'Sneaky Workspace')->exists())->toBeFalse();
});

test('a workspace admin cannot store a workspace on the shared account', function () {
    $this->actingAs($this->admin)
        ->post(route('app.workspaces.store'), [
            'name' => 'Admin Workspace',
        ])
        ->assertForbidden();

    expect(Workspace::where('name', 'Admin Workspace')->exists())->toBeFalse();
});
