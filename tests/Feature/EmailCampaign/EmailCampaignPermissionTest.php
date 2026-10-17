<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

it('requires authentication to view email campaigns', function (): void {
    $this->get(route('app.email-campaigns.index'))
        ->assertRedirect(route('login'));
});

it('lets a workspace admin open the campaign list', function (): void {
    $this->actingAs($this->owner->fresh())
        ->get(route('app.email-campaigns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('email-campaigns/Index')
            ->has('campaigns.data', 0)
            ->where('mailConfigured', false));
});

it('blocks a member without the can_email permission', function (): void {
    $member = User::factory()->create(['account_id' => $this->owner->account_id]);
    $this->workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
        'can_email' => false,
    ]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->get(route('app.email-campaigns.index'))
        ->assertForbidden();
});

it('allows a member once can_email is granted', function (): void {
    $member = User::factory()->create(['account_id' => $this->owner->account_id]);
    $this->workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
        'can_email' => true,
    ]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->get(route('app.email-campaigns.index'))
        ->assertOk();
});
