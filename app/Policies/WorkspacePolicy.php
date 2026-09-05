<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace);
    }

    public function create(User $user): bool
    {
        return $user->isAccountOwner();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        // Owner-only: deleting a workspace changes Stripe subscription quantity.
        return $this->isOwner($user, $workspace);
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    public function manageTeam(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace);
    }

    public function manageAccounts(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace);
    }

    public function connectAccounts(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace)
            || $this->hasRole($user, $workspace, [Role::Member]);
    }

    public function viewOmnichat(User $user, Workspace $workspace): bool
    {
        if ($this->isOwnerOrWorkspaceAdmin($user, $workspace)) {
            return true;
        }

        return $this->hasFeaturePermission($user, $workspace, 'can_omnichat');
    }

    public function assignConversations(User $user, Workspace $workspace): bool
    {
        if ($this->isOwnerOrWorkspaceAdmin($user, $workspace)) {
            return true;
        }

        return $this->hasFeaturePermission($user, $workspace, 'can_omnichat');
    }

    public function createPost(User $user, Workspace $workspace): bool
    {
        if ($this->isOwnerOrWorkspaceAdmin($user, $workspace)) {
            return true;
        }

        if (! $this->hasRole($user, $workspace, [Role::Member])) {
            return false;
        }

        if (! $this->hasFeaturePermission($user, $workspace, 'can_content')) {
            return false;
        }

        $hasActiveWorkflows = $workspace->contentWorkflows()
            ->where('is_active', true)
            ->exists();

        if (! $hasActiveWorkflows) {
            return true;
        }

        return $workspace->contentWorkflows()
            ->where('is_active', true)
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('users.id', $user->id)
                    ->where('content_workflow_members.can_write', true);
            })
            ->exists();
    }

    public function viewAnalytics(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace);
    }

    public function inviteMember(User $user, Workspace $workspace): bool
    {
        return $this->isOwnerOrWorkspaceAdmin($user, $workspace);
    }

    public function manageBilling(User $user, Workspace $workspace): bool
    {
        return $this->isOwner($user, $workspace);
    }

    private function isOwner(User $user, Workspace $workspace): bool
    {
        return $workspace->account_id === $user->account_id && $user->isAccountOwner();
    }

    private function isOwnerOrWorkspaceAdmin(User $user, Workspace $workspace): bool
    {
        if ($this->isOwner($user, $workspace)) {
            return true;
        }

        return $this->hasRole($user, $workspace, [Role::Admin]);
    }

    private function canAccess(User $user, Workspace $workspace): bool
    {
        if ($workspace->account_id !== $user->account_id) {
            return false;
        }

        if ($user->isAccountOwner()) {
            return true;
        }

        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    private function hasRole(User $user, Workspace $workspace, array $roles): bool
    {
        if ($workspace->account_id !== $user->account_id) {
            return false;
        }

        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        return in_array(Role::tryFrom($member->pivot->role), $roles);
    }

    private function hasFeaturePermission(User $user, Workspace $workspace, string $permission): bool
    {
        if ($workspace->account_id !== $user->account_id) {
            return false;
        }

        $member = $workspace->members()->where('user_id', $user->id)->first();

        return $member !== null && (bool) $member->pivot->{$permission};
    }
}
