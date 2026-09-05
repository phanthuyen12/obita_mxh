<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
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

test('an admin can view the assignment table with page and member permissions', function (): void {
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);
    $account->sharedUsers()->attach($this->member->id, [
        'granted_by_user_id' => $this->owner->id,
        'can_view_omnichat' => true,
        'can_access_content' => false,
        'can_create_posts' => false,
        'can_edit_posts' => false,
        'can_approve_posts' => false,
        'can_publish_posts' => false,
        'can_delete_posts' => false,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.workspace.assignments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.member.id', $this->member->id)
            ->where('rows.0.account.id', $account->id)
            ->where('rows.0.permissions.omnichat.0', 'can_view_omnichat')
            ->where('rows.0.permissions.content', []));
});

test('a member cannot view the assignment management page', function (): void {
    $this->actingAs($this->member)
        ->get(route('app.workspace.assignments'))
        ->assertForbidden();
});

test('assignment filters only return matching rows', function (): void {
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'connected_by_user_id' => $this->owner->id,
    ]);
    $account->sharedUsers()->attach($this->member->id, [
        'granted_by_user_id' => $this->owner->id,
        'can_view_omnichat' => true,
        'can_access_content' => false,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.workspace.assignments', ['module' => 'content']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows', []));
});
