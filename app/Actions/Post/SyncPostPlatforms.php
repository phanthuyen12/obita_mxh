<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Models\Post;
use App\Models\User;

class SyncPostPlatforms
{
    /**
     * Ensure the post has a post_platform row for every currently-active social
     * account in its workspace. New rows are created with enabled=false so the
     * user can opt into the additional accounts via the Schedule tab without
     * losing existing toggle state.
     */
    public static function execute(Post $post, User $user): void
    {
        $workspace = $post->workspace;

        // Auto-sync any connected WordPressSite into socialAccounts
        $wpSites = $workspace->wordPressSites()->where('status', 'connected')->get();
        foreach ($wpSites as $wpSite) {
            $workspace->socialAccounts()->firstOrCreate(
                [
                    'platform' => SocialPlatform::WordPress,
                    'platform_user_id' => $wpSite->url,
                ],
                [
                    'connected_by_user_id' => $user->id,
                    'username' => $wpSite->username,
                    'display_name' => $wpSite->name,
                    'avatar_url' => '/images/accounts/wordpress.svg',
                    'access_token' => $wpSite->application_password,
                    'status' => Status::Connected,
                    'is_active' => true,
                    'scopes' => ['publish'],
                    'meta' => [
                        'site_id' => $wpSite->id,
                        'url' => $wpSite->url,
                        'username' => $wpSite->username,
                        'categories' => $wpSite->categories_cache,
                        'tags' => $wpSite->tags_cache,
                    ],
                ],
            );
        }

        $existingAccountIds = $post->postPlatforms()->pluck('social_account_id')->filter();

        $missingAccounts = $workspace->socialAccounts()
            ->accessibleBy($user)
            ->active()
            ->whereIn('platform', SocialPlatform::publishingValues())
            ->whereNotIn('id', $existingAccountIds)
            ->get()
            ->filter(fn ($account): bool => $account->userHasAccess($user, 'can_access_content'));

        foreach ($missingAccounts as $account) {
            $post->postPlatforms()->create([
                'social_account_id' => $account->id,
                'platform' => $account->platform->value,
                'platform_name' => $account->accountDisplayName(),
                'platform_username' => $account->username,
                'platform_avatar' => $account->getRawOriginal('avatar_url'),
                'content_type' => ContentType::defaultFor($account->platform),
                'status' => PostPlatformStatus::Pending,
                'enabled' => false,
            ]);
        }
    }
}
