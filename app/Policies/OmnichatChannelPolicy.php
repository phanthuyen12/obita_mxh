<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OmnichatChannel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OmnichatChannelPolicy
{
    public function view(User $user, OmnichatChannel $channel): bool|Response
    {
        if ($channel->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageAccounts', $channel->workspace)
            || $channel->sharedUsers()->whereKey($user->id)->exists();
    }

    public function manage(User $user, OmnichatChannel $channel): bool|Response
    {
        if ($channel->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageAccounts', $channel->workspace);
    }

    public function share(User $user, OmnichatChannel $channel): bool|Response
    {
        return $this->manage($user, $channel);
    }

    public function viewOmnichat(User $user, OmnichatChannel $channel): bool|Response
    {
        if ($channel->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('viewOmnichat', $channel->workspace)
            && $channel->userHasAccess($user, 'can_view_omnichat');
    }

    public function replyOmnichat(User $user, OmnichatChannel $channel): bool|Response
    {
        if ($channel->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('viewOmnichat', $channel->workspace)
            && $channel->userHasAccess($user, 'can_reply_omnichat');
    }

    public function assignConversations(User $user, OmnichatChannel $channel): bool|Response
    {
        if ($channel->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageAccounts', $channel->workspace)
            || ($user->can('viewOmnichat', $channel->workspace)
                && $channel->userHasAccess($user, 'can_assign_conversations'));
    }
}
