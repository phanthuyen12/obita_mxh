<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OmnichatConversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OmnichatConversationPolicy
{
    public function view(User $user, OmnichatConversation $conversation): bool|Response
    {
        if ($conversation->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $conversation->socialAccount !== null
            ? $user->can('viewOmnichat', $conversation->socialAccount)
            : $user->can('viewOmnichat', $conversation->channel);
    }

    public function reply(User $user, OmnichatConversation $conversation): bool|Response
    {
        if ($conversation->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $conversation->socialAccount !== null
            ? $user->can('replyOmnichat', $conversation->socialAccount)
            : $user->can('replyOmnichat', $conversation->channel);
    }

    public function assign(User $user, OmnichatConversation $conversation): bool|Response
    {
        if ($conversation->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $conversation->socialAccount !== null
            ? $user->can('assignConversations', $conversation->socialAccount)
            : $user->can('assignConversations', $conversation->channel);
    }
}
