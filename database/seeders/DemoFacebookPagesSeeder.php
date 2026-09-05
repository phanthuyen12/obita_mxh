<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\SocialAccountGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoFacebookPagesSeeder extends Seeder
{
    use WithoutModelEvents;

    private const GROUP_COUNT = 20;

    private const PAGE_COUNT = 1000;

    public function run(): void
    {
        $targetEmail = env('DEMO_FACEBOOK_USER_EMAIL', 'admin@kingpost.com');
        $targetUser = User::query()->where('email', $targetEmail)->firstOrFail();
        $workspace = $targetUser->currentWorkspace()->firstOrFail();
        $connectedByUserId = $targetUser->id;

        $this->command?->info(
            "Seeding demo Facebook Pages for {$targetUser->email} into workspace: {$workspace->name} ({$workspace->id})",
        );

        for ($number = 1; $number <= self::PAGE_COUNT; $number++) {
            $paddedNumber = str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            SocialAccount::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'platform' => Platform::Facebook->value,
                    'platform_user_id' => "demo-facebook-page-{$paddedNumber}",
                ],
                [
                    'connected_by_user_id' => $connectedByUserId,
                    'username' => "demo.page.{$paddedNumber}",
                    'display_name' => "Facebook Demo Page {$paddedNumber}",
                    'access_token' => "demo-facebook-token-{$paddedNumber}",
                    'scopes' => Platform::Facebook->requiredPublishScopes(),
                    'meta' => ['demo' => true],
                    'status' => Status::Connected,
                    'is_active' => true,
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );
        }

        $pages = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('platform', Platform::Facebook->value)
            ->where('platform_user_id', 'like', 'demo-facebook-page-%')
            ->orderBy('platform_user_id')
            ->get(['id']);

        $now = now();
        $pages->chunk(250)->each(function ($pageChunk) use ($connectedByUserId, $now): void {
            DB::table('social_account_accesses')->upsert(
                $pageChunk->map(fn (SocialAccount $page): array => [
                    'social_account_id' => $page->id,
                    'user_id' => $connectedByUserId,
                    'granted_by_user_id' => $connectedByUserId,
                    'can_view_omnichat' => true,
                    'can_reply_omnichat' => true,
                    'can_assign_conversations' => true,
                    'can_access_content' => true,
                    'can_create_posts' => true,
                    'can_edit_posts' => true,
                    'can_approve_posts' => true,
                    'can_publish_posts' => true,
                    'can_delete_posts' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['social_account_id', 'user_id'],
                [
                    'granted_by_user_id',
                    'can_view_omnichat',
                    'can_reply_omnichat',
                    'can_assign_conversations',
                    'can_access_content',
                    'can_create_posts',
                    'can_edit_posts',
                    'can_approve_posts',
                    'can_publish_posts',
                    'can_delete_posts',
                    'updated_at',
                ],
            );
        });

        $groups = collect(range(1, self::GROUP_COUNT))->map(function (int $number) use ($workspace): SocialAccountGroup {
            $paddedNumber = str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            return SocialAccountGroup::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => "Demo Facebook - Nhóm {$paddedNumber}",
                ],
            );
        });

        $membersByGroup = $groups->mapWithKeys(
            fn (SocialAccountGroup $group): array => [$group->id => []],
        );

        mt_srand(20260824);
        $pages->values()->each(function (SocialAccount $page, int $index) use ($groups, $membersByGroup): void {
            $groupIndexes = collect([$index % self::GROUP_COUNT]);
            $additionalGroupCount = mt_rand(0, 2);

            while ($groupIndexes->count() < $additionalGroupCount + 1) {
                $groupIndexes->push(mt_rand(0, self::GROUP_COUNT - 1));
                $groupIndexes = $groupIndexes->unique()->values();
            }

            $groupIndexes->each(function (int $groupIndex) use ($groups, $membersByGroup, $page): void {
                $groupId = $groups->get($groupIndex)->id;
                $membersByGroup->put(
                    $groupId,
                    [...$membersByGroup->get($groupId), $page->id],
                );
            });
        });

        $groups->each(function (SocialAccountGroup $group) use ($membersByGroup): void {
            $group->socialAccounts()->syncOrFail(
                $membersByGroup->get($group->id),
            );
        });

        $this->command?->info(
            sprintf(
                'Ready: %d demo Facebook Pages across %d random groups, with full Admin access.',
                $pages->count(),
                $groups->count(),
            ),
        );
    }
}
