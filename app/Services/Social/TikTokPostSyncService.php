<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostMetricSnapshot;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TikTokPostSyncService
{
    use HasSocialHttpClient;

    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.tiktok.api');
    }

    /** @return array{fetched: int, imported: int, updated: int} */
    public function sync(SocialAccount $account, string $userId): array
    {
        if ($account->needsProactiveTokenRefresh()) {
            app(ConnectionVerifier::class)->refreshToken($account);
        }

        $this->accessToken = $account->access_token;

        $videoListResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/video/list/?fields=id,title,create_time,cover_image_url,share_url", [
                'max_count' => 20,
            ]);

        if ($videoListResponse->failed()) {
            Log::warning('TikTok sync video list fetch failed', [
                'body' => $this->redactResponseBody($videoListResponse->body()),
            ]);

            return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
        }

        $videos = data_get($videoListResponse->json(), 'data.videos', []);

        if (empty($videos)) {
            return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
        }

        $videoIds = array_map(fn ($v) => $v['id'], $videos);

        $queryResponse = $this->getHttpClient()
            ->post("{$this->baseUrl}/video/query/?fields=id,like_count,comment_count,share_count,view_count", [
                'filters' => ['video_ids' => $videoIds],
            ]);

        if ($queryResponse->failed()) {
            Log::warning('TikTok sync video query failed', [
                'body' => $this->redactResponseBody($queryResponse->body()),
            ]);

            return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
        }

        $videoDetails = data_get($queryResponse->json(), 'data.videos', []);

        $statistics = [];
        foreach ($videoDetails as $detail) {
            $statistics[$detail['id']] = $detail;
        }

        $imported = 0;
        $updated = 0;

        foreach ($videos as $video) {
            $platformPostId = (string) data_get($video, 'id', '');
            if ($platformPostId === '') {
                continue;
            }

            $stats = $statistics[$platformPostId] ?? [];
            $content = (string) data_get($video, 'title', '');

            // create_time is a Unix timestamp in seconds
            $createTime = data_get($video, 'create_time');
            $publishedAt = $createTime ? Carbon::createFromTimestamp($createTime) : now();

            $permalinkUrl = data_get($video, 'share_url');
            $thumbnailUrl = data_get($video, 'cover_image_url');

            $result = DB::transaction(function () use ($account, $platformPostId, $userId, $content, $publishedAt, $permalinkUrl, $thumbnailUrl, $stats): string {
                $postPlatform = PostPlatform::query()
                    ->where('social_account_id', $account->id)
                    ->where('platform_post_id', $platformPostId)
                    ->first();

                $media = [];
                if ($thumbnailUrl) {
                    $media = [[
                        'id' => $platformPostId,
                        'path' => $thumbnailUrl,
                        'url' => $thumbnailUrl,
                        'mime_type' => 'image/jpeg',
                        'original_filename' => 'tiktok-cover.jpg',
                    ]];
                }

                if ($postPlatform) {
                    $postPlatform->update([
                        'platform_url' => $permalinkUrl,
                        'published_at' => $publishedAt,
                    ]);

                    if ($postPlatform->post->content !== $content || $postPlatform->post->published_at?->ne($publishedAt)) {
                        $postPlatform->post()->update([
                            'content' => $content,
                            'published_at' => $publishedAt,
                        ]);
                        if (empty($postPlatform->post->media) && ! empty($media)) {
                            $postPlatform->post()->update(['media' => $media]);
                        }
                    }

                    $this->storeSnapshot($postPlatform, $stats);

                    return 'updated';
                }

                $post = Post::withoutEvents(fn () => Post::query()->create([
                    'workspace_id' => $account->workspace_id,
                    'user_id' => $userId,
                    'content' => $content,
                    'media' => $media,
                    'status' => PostStatus::Published,
                    'published_at' => $publishedAt,
                ]));

                $postPlatform = $post->postPlatforms()->create([
                    'social_account_id' => $account->id,
                    'platform' => Platform::TikTok,
                    'platform_name' => $account->accountDisplayName(),
                    'platform_username' => $account->username,
                    'platform_avatar' => $account->getRawOriginal('avatar_url'),
                    'content_type' => ContentType::TikTokVideo,
                    'status' => PostPlatformStatus::Published,
                    'platform_post_id' => $platformPostId,
                    'enabled' => true,
                    'platform_url' => $permalinkUrl,
                    'published_at' => $publishedAt,
                    'meta' => ['source' => 'tiktok_sync'],
                ]);

                $this->storeSnapshot($postPlatform, $stats);

                return 'imported';
            });

            $result === 'imported' ? $imported++ : $updated++;
        }

        return ['fetched' => count($videos), 'imported' => $imported, 'updated' => $updated];
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp()->asJson()->withToken($this->accessToken);
    }

    private function storeSnapshot(PostPlatform $postPlatform, array $stats): void
    {
        $metrics = [];

        if (isset($stats['view_count'])) {
            $metrics[] = ['key' => 'views', 'label' => __('analytics.metrics.views'), 'value' => (int) $stats['view_count']];
        }
        if (isset($stats['like_count'])) {
            $metrics[] = ['key' => 'reactions', 'label' => __('analytics.metrics.likes'), 'value' => (int) $stats['like_count']];
        }
        if (isset($stats['comment_count'])) {
            $metrics[] = ['key' => 'comments', 'label' => __('analytics.metrics.comments'), 'value' => (int) $stats['comment_count']];
        }
        if (isset($stats['share_count'])) {
            $metrics[] = ['key' => 'shares', 'label' => __('analytics.metrics.shares'), 'value' => (int) $stats['share_count']];
        }

        if (empty($metrics)) {
            return;
        }

        PostMetricSnapshot::query()->updateOrCreate(
            [
                'post_platform_id' => $postPlatform->id,
                'captured_at' => now()->startOfMinute(),
            ],
            ['metrics' => $metrics],
        );

        Cache::forget("post_metrics:{$postPlatform->id}");
    }
}
