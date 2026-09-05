<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Enums\Omnichat\ChannelProvider;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use App\Models\User;

class OmnichatChannelAccessChannel
{
    public function join(User $user, string $channelId): bool
    {
        $socialAccount = SocialAccount::query()->find($channelId);

        if ($socialAccount !== null) {
            return $user->current_workspace_id === $socialAccount->workspace_id
                && $user->omnichatViewSocialAccounts()->whereKey($socialAccount->id)->exists()
                && $socialAccount->workspace->hasMember($user);
        }

        $channel = OmnichatChannel::query()
            ->whereKey($channelId)
            ->where('workspace_id', $user->current_workspace_id)
            ->where('provider', ChannelProvider::Website)
            ->first();

        return $channel !== null && $user->can('viewOmnichat', $channel);
    }
}
