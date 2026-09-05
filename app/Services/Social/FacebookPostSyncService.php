<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Actions\Tag\SyncTags;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostMetricSnapshot;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Meta\GraphPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacebookPostSyncService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.facebook.graph_api');
    }

    /** @return array{fetched: int, imported: int, updated: int} */
    public function sync(SocialAccount $account, string $userId): array
    {
        Log::info("Starting Facebook post sync for account: {$account->display_name} ({$account->id})");

        $items = GraphPaginator::all(
            "{$this->baseUrl}/{$account->platform_user_id}/feed",
            [
                'fields' => 'id,message,created_time,permalink_url,full_picture,shares,comments.summary(true).filter(stream).limit(0),reactions.summary(true).limit(0),likes.summary(true).limit(0)',
                'limit' => 50,
                'since' => now()->subDays(180)->timestamp,
                'access_token' => $account->access_token,
            ],
        );

        Log::info('Facebook feed returned '.count($items)." posts to process for account: {$account->display_name}");

        $imported = 0;
        $updated = 0;

        foreach ($items as $index => $item) {
            $platformPostId = (string) data_get($item, 'id', '');
            $postNum = $index + 1;
            $totalPosts = count($items);

            if ($platformPostId === '') {
                continue;
            }

            $result = DB::transaction(function () use ($account, $item, $platformPostId, $userId, $postNum, $totalPosts): string {
                $postPlatform = PostPlatform::query()
                    ->where('social_account_id', $account->id)
                    ->where(function ($query) use ($platformPostId): void {
                        $query->where('platform_post_id', $platformPostId);
                        if (str_contains($platformPostId, '_')) {
                            $shortId = explode('_', $platformPostId)[1] ?? '';
                            if ($shortId !== '') {
                                $query->orWhere('platform_post_id', $shortId);
                            }
                        } else {
                            $query->orWhere('platform_post_id', 'like', "%_{$platformPostId}");
                        }
                    })
                    ->first();

                $publishedAt = Carbon::parse(data_get($item, 'created_time', now()->toIso8601String()));
                $content = data_get($item, 'message');
                $trimmedContent = trim((string) ($content ?? ''));

                if (! $postPlatform && $trimmedContent !== '') {
                    $postPlatform = PostPlatform::query()
                        ->where('social_account_id', $account->id)
                        ->whereHas('post', function ($q) use ($account, $trimmedContent, $publishedAt): void {
                            $q->where('workspace_id', $account->workspace_id)
                                ->whereRaw('TRIM(content) = ?', [$trimmedContent])
                                ->whereBetween('published_at', [
                                    $publishedAt->copy()->subHours(24),
                                    $publishedAt->copy()->addHours(24),
                                ]);
                        })
                        ->first();
                }

                if ($postPlatform) {
                    if ($trimmedContent !== '') {
                        $duplicatePlatforms = PostPlatform::query()
                            ->where('social_account_id', $account->id)
                            ->where('id', '!=', $postPlatform->id)
                            ->whereHas('post', function ($q) use ($account, $trimmedContent, $publishedAt): void {
                                $q->where('workspace_id', $account->workspace_id)
                                    ->whereRaw('TRIM(content) = ?', [$trimmedContent])
                                    ->whereBetween('published_at', [
                                        $publishedAt->copy()->subHours(48),
                                        $publishedAt->copy()->addHours(48),
                                    ]);
                            })
                            ->get();

                        foreach ($duplicatePlatforms as $dupPlatform) {
                            $dupPost = $dupPlatform->post;
                            if ($dupPost && $dupPost->id !== $postPlatform->post_id) {
                                if ($dupPost->is_ceo_content && ! $postPlatform->post->is_ceo_content) {
                                    $mergedTags = array_values(array_unique(array_merge(
                                        $postPlatform->post->topic_tags ?? [],
                                        $dupPost->topic_tags ?? ['CEO']
                                    )));
                                    $postPlatform->post()->update(['is_ceo_content' => true]);
                                    SyncTags::execute(
                                        $postPlatform->post->workspace,
                                        $postPlatform->post,
                                        $mergedTags,
                                        detachMissing: false,
                                    );
                                }
                                foreach ($dupPlatform->snapshots as $dupSnapshot) {
                                    $exists = PostMetricSnapshot::query()
                                        ->where('post_platform_id', $postPlatform->id)
                                        ->where('captured_at', $dupSnapshot->captured_at)
                                        ->exists();

                                    if ($exists) {
                                        $dupSnapshot->delete();
                                    } else {
                                        $dupSnapshot->update(['post_platform_id' => $postPlatform->id]);
                                    }
                                }
                                $dupPlatform->delete();
                                $dupPost->delete();
                            }
                        }
                    }

                    $postPlatform->update([
                        'platform_post_id' => $platformPostId,
                        'platform_url' => data_get($item, 'permalink_url'),
                        'published_at' => $publishedAt,
                    ]);

                    $postPlatform->post()->update([
                        'published_at' => $publishedAt,
                    ]);

                    $this->storeSnapshot($postPlatform, $item, $postNum, $totalPosts);

                    return 'updated';
                }

                $post = Post::withoutEvents(fn () => Post::query()->create([
                    'workspace_id' => $account->workspace_id,
                    'user_id' => $userId,
                    'content' => $content,
                    'media' => $this->mediaFromFacebookItem($item),
                    'status' => PostStatus::Published,
                    'published_at' => $publishedAt,
                ]));

                $postPlatform = $post->postPlatforms()->create([
                    'social_account_id' => $account->id,
                    'platform' => Platform::Facebook,
                    'platform_name' => $account->accountDisplayName(),
                    'platform_username' => $account->username,
                    'platform_avatar' => $account->getRawOriginal('avatar_url'),
                    'content_type' => ContentType::FacebookPost,
                    'status' => PostPlatformStatus::Published,
                    'platform_post_id' => $platformPostId,
                    'enabled' => true,
                    'platform_url' => data_get($item, 'permalink_url'),
                    'published_at' => $publishedAt,
                    'meta' => ['source' => 'facebook_sync'],
                ]);
                $this->storeSnapshot($postPlatform, $item, $postNum, $totalPosts);

                return 'imported';
            });

            $result === 'imported' ? $imported++ : $updated++;
        }

        Log::info("Completed Facebook post sync for account {$account->display_name}: fetched=".count($items).", imported={$imported}, updated={$updated}");

        return ['fetched' => count($items), 'imported' => $imported, 'updated' => $updated];
    }

    /** @return array<int, array<string, string>> */
    private function mediaFromFacebookItem(array $item): array
    {
        $url = data_get($item, 'full_picture');

        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        return [
            [
                'id' => (string) data_get($item, 'id'),
                'path' => $url,
                'url' => $url,
                'mime_type' => 'image/jpeg',
                'original_filename' => 'facebook-post-image.jpg',
            ],
        ];
    }

    private function storeSnapshot(PostPlatform $postPlatform, array $item, int $current = 0, int $total = 0): void
    {
        $reactions = data_get($item, 'reactions.summary.total_count')
            ?? data_get($item, 'likes.summary.total_count')
            ?? 0;

        $comments = data_get($item, 'comments.summary.total_count')
            ?? data_get($item, 'comments.count')
            ?? 0;

        $shares = data_get($item, 'shares.count') ?? 0;

        // Always fetch insights for reach/impressions, but pass feed metrics to avoid double API calls.
        if ($postPlatform->platform_post_id) {
            try {
                $liveMetrics = app(FacebookAnalytics::class)->fetchPostMetrics($postPlatform, $item);
                if (is_array($liveMetrics) && ! isset($liveMetrics['unsupported'])) {
                    PostMetricSnapshot::query()->updateOrCreate(
                        [
                            'post_platform_id' => $postPlatform->id,
                            'captured_at' => now()->startOfMinute(),
                        ],
                        ['metrics' => $liveMetrics],
                    );
                    Cache::forget("post_metrics:{$postPlatform->id}");

                    $metricMap = collect($liveMetrics)->keyBy('key');
                    $reach = $metricMap->get('reach')['value'] ?? 0;
                    $impressions = $metricMap->get('impressions')['value'] ?? 0;
                    $reactions = $metricMap->get('reactions')['value'] ?? $reactions;
                    $comments = $metricMap->get('comments')['value'] ?? $comments;
                    $shares = $metricMap->get('shares')['value'] ?? $shares;

                    Log::info("[{$current}/{$total}] Post {$postPlatform->platform_post_id} synced: Reach={$reach}, Imp={$impressions}, Reaction={$reactions}, Cmt={$comments}, Share={$shares}");

                    return;
                }
            } catch (\Throwable $e) {
                Log::warning("[{$current}/{$total}] Post {$postPlatform->platform_post_id} insight error: ".$e->getMessage());
            }
        }

        $metrics = [
            ['key' => 'reach', 'label' => 'Reach', 'value' => 0],
            ['key' => 'impressions', 'label' => 'Impressions', 'value' => 0],
            ['key' => 'reactions', 'label' => 'Reactions', 'value' => (int) $reactions],
            ['key' => 'comments', 'label' => 'Comments', 'value' => (int) $comments],
            ['key' => 'shares', 'label' => 'Shares', 'value' => (int) $shares],
        ];

        PostMetricSnapshot::query()->updateOrCreate(
            [
                'post_platform_id' => $postPlatform->id,
                'captured_at' => now()->startOfMinute(),
            ],
            ['metrics' => $metrics],
        );

        Cache::forget("post_metrics:{$postPlatform->id}");
        Log::info("[{$current}/{$total}] Post {$postPlatform->platform_post_id} saved (feed only): Reaction={$reactions}, Cmt={$comments}, Share={$shares}");
    }
}
