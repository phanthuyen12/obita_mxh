<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Resolves the set of channels a user may view inside a workspace's omnichat,
 * mirroring the access rules used by the Inbox. Shared by the Inbox page and
 * the sale-facing LiveChat so both stay in sync on channel visibility.
 */
class AccessibleChannelResolver
{
    /**
     * @return array{
     *     accounts: Collection<int, SocialAccount>,
     *     customChannels: Collection<int, OmnichatChannel>,
     *     connectedChannels: array<int, array<string, mixed>>,
     *     selectedChannelIds: array<int, int|string>,
     *     customChannelIds: array<int, int|string>,
     * }
     */
    public function resolve(User $user, Workspace $workspace): array
    {
        $accounts = $workspace->socialAccounts()
            ->omnichatAccessibleBy($user)
            ->with('sharedUsers')
            ->orderBy('display_name')
            ->get();

        $connectedChannels = $accounts->map(fn (SocialAccount $account): array => [
            'id' => $account->id,
            'provider' => $account->platform->network(),
            'name' => $account->display_label,
            'avatar_url' => $account->avatar_url,
            'status' => $account->status->value,
            'is_active' => $account->is_active,
        ])->values();

        $customChannels = OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('provider', [ChannelProvider::Website, ChannelProvider::Telegram])
            ->connected()
            ->when(
                ! $user->can('manageAccounts', $workspace),
                fn ($query) => $query->whereHas('sharedUsers', fn ($query) => $query
                    ->whereKey($user->id)
                    ->where('omnichat_channel_accesses.can_view_omnichat', true)),
            )
            ->orderBy('name')
            ->get();

        $connectedChannels = $connectedChannels->concat($customChannels->map(fn (OmnichatChannel $channel): array => [
            'id' => $channel->id,
            'provider' => $channel->provider->value,
            'name' => $channel->name,
            'avatar_url' => $channel->avatar_url,
            'status' => $channel->status->value,
            'is_active' => true,
        ]))->values()->all();

        $availableChannelIds = $accounts->pluck('id');
        $savedChannelIds = $user->omnichatViewSocialAccounts()
            ->whereIn((new SocialAccount)->qualifyColumn('id'), $availableChannelIds)
            ->pluck((new SocialAccount)->qualifyColumn('id'))
            ->values()
            ->all();

        if ($savedChannelIds === []) {
            $selectedChannelIds = $accounts->where('is_active', true)->pluck('id')->values()->all();
            if ($selectedChannelIds === []) {
                $selectedChannelIds = $availableChannelIds->values()->all();
            }
            $user->omnichatViewSocialAccounts()->sync($selectedChannelIds);
        } else {
            $activeAccountIds = $accounts->where('is_active', true)->pluck('id')->values()->all();
            $selectedChannelIds = array_values(array_unique(array_merge($savedChannelIds, $activeAccountIds)));
            $user->omnichatViewSocialAccounts()->sync($selectedChannelIds);
        }

        $customChannelIds = $customChannels->pluck('id')->all();
        $selectedChannelIds = array_values(array_unique(array_merge($selectedChannelIds, $customChannelIds)));

        return [
            'accounts' => $accounts,
            'customChannels' => $customChannels,
            'connectedChannels' => $connectedChannels,
            'selectedChannelIds' => $selectedChannelIds,
            'customChannelIds' => $customChannelIds,
        ];
    }
}
