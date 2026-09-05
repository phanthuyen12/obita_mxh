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
use Google\Client as GoogleClient;
use Google\Service\YouTube;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YouTubePostSyncService
{
    /** @return array{fetched: int, imported: int, updated: int} */
    public function sync(SocialAccount $account, string $userId): array
    {
        $client = $this->createGoogleClient($account);
        $youtube = new YouTube($client);

        try {
            $channelsResponse = $youtube->channels->listChannels('contentDetails', ['mine' => true]);
            $channels = $channelsResponse->getItems();

            if (empty($channels)) {
                return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
            }

            $uploadsPlaylistId = $channels[0]->getContentDetails()->getRelatedPlaylists()->getUploads();

            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', [
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => 50, // max allowed
            ]);

            $items = $playlistItemsResponse->getItems();

            if (empty($items)) {
                return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
            }

            $videoIds = [];
            foreach ($items as $item) {
                $videoIds[] = $item->getSnippet()->getResourceId()->getVideoId();
            }

            $videosResponse = $youtube->videos->listVideos('snippet,statistics', [
                'id' => implode(',', $videoIds),
            ]);

            $videos = [];
            foreach ($videosResponse->getItems() as $video) {
                $videos[$video->getId()] = $video;
            }

            $imported = 0;
            $updated = 0;

            foreach ($items as $item) {
                $videoId = $item->getSnippet()->getResourceId()->getVideoId();
                if (! isset($videos[$videoId])) {
                    continue;
                }

                $video = $videos[$videoId];
                $snippet = $video->getSnippet();
                $statistics = $video->getStatistics();

                $platformPostId = $videoId;
                $content = $snippet->getTitle()."\n\n".$snippet->getDescription();
                $publishedAt = Carbon::parse($snippet->getPublishedAt());
                $permalinkUrl = "https://www.youtube.com/watch?v={$videoId}";
                $thumbnailUrl = $snippet->getThumbnails()->getHigh() ? $snippet->getThumbnails()->getHigh()->getUrl() : null;

                $result = DB::transaction(function () use ($account, $platformPostId, $userId, $content, $publishedAt, $permalinkUrl, $thumbnailUrl, $statistics): string {
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
                            'original_filename' => 'youtube-thumbnail.jpg',
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

                        $this->storeSnapshot($postPlatform, $statistics);

                        return 'updated';
                    }

                    $post = Post::withoutEvents(fn () => Post::query()->create([
                        'workspace_id' => $account->workspace_id,
                        'user_id' => $userId,
                        'content' => $content,
                        'media' => $media,
                        'status' => PostStatus::Published,
                        'published_at' => $publishedAt,
                        'is_ceo_content' => false,
                    ]));

                    $postPlatform = $post->postPlatforms()->create([
                        'social_account_id' => $account->id,
                        'platform' => Platform::YouTube,
                        'platform_name' => $account->accountDisplayName(),
                        'platform_username' => $account->username,
                        'platform_avatar' => $account->getRawOriginal('avatar_url'),
                        'content_type' => ContentType::YouTubeShort,
                        'status' => PostPlatformStatus::Published,
                        'platform_post_id' => $platformPostId,
                        'enabled' => true,
                        'platform_url' => $permalinkUrl,
                        'published_at' => $publishedAt,
                        'meta' => ['source' => 'youtube_sync'],
                    ]);

                    $this->storeSnapshot($postPlatform, $statistics);

                    return 'imported';
                });

                $result === 'imported' ? $imported++ : $updated++;
            }

            return ['fetched' => count($items), 'imported' => $imported, 'updated' => $updated];

        } catch (\Throwable $e) {
            Log::error('YouTube Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['fetched' => 0, 'imported' => 0, 'updated' => 0];
        }
    }

    private function createGoogleClient(SocialAccount $account): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        $remainingSeconds = $account->token_expires_at
            ? max(0, (int) now()->diffInSeconds($account->token_expires_at, false))
            : 3600;

        $tokenData = [
            'access_token' => $account->access_token,
            'created' => time(),
            'expires_in' => $remainingSeconds,
        ];

        if ($account->refresh_token) {
            $tokenData['refresh_token'] = $account->refresh_token;
        }

        $client->setAccessToken($tokenData);

        return $client;
    }

    private function storeSnapshot(PostPlatform $postPlatform, $statistics): void
    {
        $metrics = [];

        if ($statistics->getViewCount() !== null) {
            $metrics[] = ['key' => 'views', 'label' => __('analytics.metrics.views'), 'value' => (int) $statistics->getViewCount()];
        }
        if ($statistics->getLikeCount() !== null) {
            $metrics[] = ['key' => 'reactions', 'label' => __('analytics.metrics.likes'), 'value' => (int) $statistics->getLikeCount()];
        }
        if ($statistics->getCommentCount() !== null) {
            $metrics[] = ['key' => 'comments', 'label' => __('analytics.metrics.comments'), 'value' => (int) $statistics->getCommentCount()];
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
