<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Authorize viewing a post. The post must live in the user's current
     * workspace; cross-workspace lookups deny as 404 so we don't leak
     * existence across tenants.
     */
    public function view(User $user, Post $post): bool|Response
    {
        if ($post->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }

    /**
     * Authorize updating a post: tenancy guard (404 across tenants) then the
     * role gate — viewers are read-only (403).
     */
    public function update(User $user, Post $post): bool|Response
    {
        if ($post->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        if ($user->can('manageTeam', $user->currentWorkspace)) {
            return true;
        }

        if ($post->content_workflow_id === null) {
            return $user->can('createPost', $user->currentWorkspace);
        }

        return $post->contentWorkflow()
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('users.id', $user->id)
                    ->where(function ($permissions): void {
                        $permissions->where('content_workflow_members.can_write', true)
                            ->orWhere('content_workflow_members.can_review', true);
                    });
            })
            ->exists();
    }

    /**
     * Authorize deleting a post: tenancy guard (404 across tenants) then the
     * same role gate as `update` — viewers are read-only (403).
     */
    public function delete(User $user, Post $post): bool|Response
    {
        if ($post->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('createPost', $user->currentWorkspace);
    }

    /**
     * Authorize duplicating a post into the user's current workspace as a
     * fresh draft. The post must live in the user's current workspace
     * (404 otherwise — tenancy guard, see `view()`) AND the user must
     * have permission to create posts there (403 otherwise).
     */
    public function duplicate(User $user, Post $post): bool|Response
    {
        if ($post->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('createPost', $user->currentWorkspace);
    }
}
