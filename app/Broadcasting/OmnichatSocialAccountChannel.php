<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\SocialAccount;
use App\Models\User;

class OmnichatSocialAccountChannel
{
    public function join(User $user, SocialAccount $account): bool
    {
        return $user->current_workspace_id === $account->workspace_id
            && $user->omnichatViewSocialAccounts()->whereKey($account->id)->exists()
            && $account->workspace->hasMember($user);
    }
}
