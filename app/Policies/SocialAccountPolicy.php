<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class SocialAccountPolicy
{
    /**
     * Authorize access to a social account. Must live in the user's current
     * workspace; cross-workspace lookups deny as 404 so we don't leak
     * existence across tenants (same tenancy pattern as PostPolicy).
     */
    public function view(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        if ($user->can('manageAccounts', $this->workspace($account))) {
            return true;
        }

        return $account->connected_by_user_id === $user->id
            || $account->sharedUsers()->whereKey($user->id)->exists();
    }

    public function manage(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $account->connected_by_user_id === $user->id
            || $user->can('manageAccounts', $this->workspace($account));
    }

    public function share(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageAccounts', $this->workspace($account));
    }

    public function viewOmnichat(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('viewOmnichat', $this->workspace($account))
            && $account->userHasAccess($user, 'can_view_omnichat');
    }

    public function replyOmnichat(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('viewOmnichat', $this->workspace($account))
            && $account->userHasAccess($user, 'can_reply_omnichat');
    }

    public function assignConversations(User $user, SocialAccount $account): bool|Response
    {
        if ($account->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageAccounts', $this->workspace($account))
            || ($user->can('viewOmnichat', $this->workspace($account))
                && $account->userHasAccess($user, 'can_assign_conversations'));
    }

    private function workspace(SocialAccount $account): Workspace
    {
        $account->loadMissing('workspace');

        return $account->getRelation('workspace');
    }
}
