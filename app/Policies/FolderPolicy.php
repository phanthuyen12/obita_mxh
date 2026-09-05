<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Folder\Permission;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Folder $folder): bool|Response
    {
        return $this->outsideCurrentWorkspace($user, $folder)
            ?? $folder->userHasPermission($user, Permission::View);
    }

    /**
     * Determine whether the user can create models.
     */
    public function createMaster(User $user): bool
    {
        return $user->currentWorkspace !== null
            && $user->can('manageTeam', $user->currentWorkspace);
    }

    public function createChild(User $user, Folder $parent): bool|Response
    {
        return $this->outsideCurrentWorkspace($user, $parent)
            ?? (! $parent->is_locked && $parent->userHasPermission($user, Permission::CreateFolder));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Folder $folder): bool|Response
    {
        $workspaceCheck = $this->outsideCurrentWorkspace($user, $folder);

        if ($workspaceCheck instanceof Response) {
            return $workspaceCheck;
        }

        if ($folder->isMaster()) {
            return $user->can('manageTeam', $user->currentWorkspace);
        }

        if ($user->can('manageTeam', $user->currentWorkspace)) {
            return true;
        }

        return ! $folder->is_locked && $folder->userHasPermission($user, Permission::ManageFolder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Folder $folder): bool|Response
    {
        return $this->update($user, $folder);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Folder $folder): bool|Response
    {
        return $this->update($user, $folder);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Folder $folder): bool|Response
    {
        $workspaceCheck = $this->outsideCurrentWorkspace($user, $folder);

        return $workspaceCheck instanceof Response
            ? $workspaceCheck
            : $user->can('manageTeam', $user->currentWorkspace);
    }

    public function managePermissions(User $user, Folder $folder): bool|Response
    {
        $workspaceCheck = $this->outsideCurrentWorkspace($user, $folder);

        return $workspaceCheck instanceof Response
            ? $workspaceCheck
            : $folder->owner_user_id === $user->id
                || $user->can('manageTeam', $user->currentWorkspace);
    }

    public function uploadMedia(User $user, Folder $folder): bool|Response
    {
        return $this->outsideCurrentWorkspace($user, $folder)
            ?? $folder->userHasPermission($user, Permission::UploadMedia);
    }

    public function createPost(User $user, Folder $folder): bool|Response
    {
        return $this->outsideCurrentWorkspace($user, $folder)
            ?? $folder->userHasPermission($user, Permission::View);
    }

    private function outsideCurrentWorkspace(User $user, Folder $folder): ?Response
    {
        return $folder->workspace_id !== $user->current_workspace_id
            ? Response::denyAsNotFound()
            : null;
    }
}
