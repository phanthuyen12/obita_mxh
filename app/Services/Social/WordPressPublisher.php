<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Exceptions\SocialPublishException;
use App\Models\PostPlatform;
use App\Models\WordPressSite;
use App\Services\WordPress\WordPressApiClient;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordPressPublisher
{
    public function __construct(
        private readonly WordPressApiClient $wpClient,
    ) {}

    /**
     * Publish a post to a WordPress site via REST API.
     *
     * @return array{id: string|int, url: string}
     */
    public function publish(PostPlatform $postPlatform): array
    {
        $post = $postPlatform->post;
        $workspace = $postPlatform->socialAccount->workspace ?? $post->workspace;

        // 1. Locate the WordPress site corresponding to this social account or URL
        $metaSiteId = $postPlatform->socialAccount->meta['site_id'] ?? null;
        $platformUserId = $postPlatform->socialAccount->platform_user_id ?? '';

        $site = WordPressSite::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($metaSiteId, $platformUserId, $postPlatform): void {
                if ($metaSiteId) {
                    $query->orWhere('id', $metaSiteId);
                }
                if ($platformUserId) {
                    $query->orWhere('url', $platformUserId)
                        ->orWhere('url', 'like', '%'.$platformUserId.'%');
                }
                $query->orWhere('id', $postPlatform->social_account_id)
                    ->orWhere('username', $postPlatform->socialAccount->username ?? '')
                    ->orWhere('url', 'like', '%'.($postPlatform->socialAccount->username ?? '').'%');
            })
            ->first();

        if (! $site) {
            // Fallback to first connected site in workspace
            $site = WordPressSite::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'connected')
                ->first();
        }

        if (! $site) {
            throw SocialPublishException::generic(
                'Không tìm thấy cấu hình website WordPress hoặc website chưa được kết nối.',
                $postPlatform->platform,
            );
        }

        $meta = $postPlatform->meta ?? [];
        $rawContent = $post->content ?? '';

        // 2. Resolve Title
        $title = ! empty($meta['title']) ? trim((string) $meta['title']) : '';
        if ($title === '') {
            $firstLine = trim((string) Str::of($rawContent)->explode("\n")->first());
            $title = Str::limit($firstLine ?: 'Bài viết mới', 120, '');
        }

        // 3. Resolve Content (Preserve HTML tags if using Block Editor, otherwise convert linebreaks)
        $body = $rawContent;
        if (! empty($meta['title']) && Str::startsWith($body, $meta['title'])) {
            $body = trim((string) Str::after($body, $meta['title']));
        }
        $formattedContent = preg_match('/<(h[1-6]|p|blockquote|ul|ol|li|img|figure|div|table)[\s\S]*>/i', $body)
            ? $body
            : nl2br(e($body));

        // 4. Payload for WP REST API
        $payload = [
            'title' => $title,
            'content' => $formattedContent,
            'status' => $meta['status'] ?? 'publish',
            'comment_status' => $meta['comment_status'] ?? 'open',
        ];

        if (! empty($meta['slug'])) {
            $payload['slug'] = Str::slug((string) $meta['slug']);
        }

        if (! empty($meta['excerpt'])) {
            $payload['excerpt'] = (string) $meta['excerpt'];
        }

        // 5. Featured Media Upload
        $mediaList = $post->media ?? [];
        if (! empty($mediaList) && is_array($mediaList)) {
            $firstMedia = $mediaList[0];
            $mediaUrl = is_array($firstMedia) ? ($firstMedia['url'] ?? null) : ($firstMedia->url ?? null);
            if ($mediaUrl) {
                $uploadedMediaId = $this->wpClient->uploadMedia(
                    $site->url,
                    $site->username,
                    (string) $site->application_password,
                    (string) $mediaUrl,
                    is_array($firstMedia) ? ($firstMedia['original_filename'] ?? null) : null,
                );
                if ($uploadedMediaId) {
                    $payload['featured_media'] = $uploadedMediaId;
                }
            }
        }

        // 6. Categories resolution
        if (! empty($meta['categories'])) {
            $catIds = $this->resolveCategoryIds($site, $meta['categories']);
            if (! empty($catIds)) {
                $payload['categories'] = $catIds;
            }
        }

        // 7. Tags resolution
        if (! empty($meta['tags'])) {
            $tagIds = $this->resolveTagIds($site, $meta['tags']);
            if (! empty($tagIds)) {
                $payload['tags'] = $tagIds;
            }
        }

        try {
            $response = $this->wpClient->sendRequest(
                $site->url,
                $site->username,
                (string) $site->application_password,
                'posts',
                'post',
                $payload,
            );

            if ($response->successful()) {
                $createdPost = $response->json();
                $postId = is_array($createdPost) ? ($createdPost['id'] ?? null) : null;

                if (! is_int($postId) && ! ctype_digit((string) $postId)) {
                    Log::error('WordPress publish returned an invalid response', [
                        'site_id' => $site->id,
                        'content_type' => $response->header('Content-Type'),
                        'response' => $response->body(),
                    ]);

                    throw SocialPublishException::generic(
                        'Website WordPress trả về phản hồi không hợp lệ khi tạo bài viết.',
                        $postPlatform->platform,
                    );
                }

                $postLink = $createdPost['link'] ?? "{$site->url}/?p={$postId}";

                return [
                    'id' => (string) $postId,
                    'url' => (string) $postLink,
                ];
            }

            $errorMsg = $response->json('message') ?? 'HTTP '.$response->status().' - Không thể đăng bài lên WordPress.';
            Log::error('WordPress publish failed', [
                'site_id' => $site->id,
                'response' => $response->body(),
            ]);

            throw SocialPublishException::generic($errorMsg, $postPlatform->platform);
        } catch (Exception $e) {
            if ($e instanceof SocialPublishException) {
                throw $e;
            }

            Log::error('WordPress publish error', [
                'site_id' => $site->id,
                'error' => $e->getMessage(),
            ]);

            throw SocialPublishException::generic(
                'Lỗi khi xuất bản lên WordPress: '.$e->getMessage(),
                $postPlatform->platform,
            );
        }
    }

    /**
     * Map category names or IDs to valid category IDs on the WordPress site.
     *
     * @param  array<int, mixed>|string  $categories
     * @return array<int, int>
     */
    private function resolveCategoryIds(WordPressSite $site, array|string $categories): array
    {
        $cachedCategories = collect($site->categories_cache ?? []);
        $catList = is_array($categories) ? $categories : explode(',', (string) $categories);
        $ids = [];

        foreach ($catList as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') {
                continue;
            }

            if (is_numeric($trimmed)) {
                $ids[] = (int) $trimmed;

                continue;
            }

            $matched = $cachedCategories->first(
                fn ($c) => mb_strtolower($c['name'] ?? '') === mb_strtolower($trimmed)
                    || ($c['slug'] ?? '') === Str::slug($trimmed),
            );

            if ($matched && ! empty($matched['id'])) {
                $ids[] = (int) $matched['id'];
            } else {
                try {
                    $res = $this->wpClient->sendRequest(
                        $site->url,
                        $site->username,
                        (string) $site->application_password,
                        'categories',
                        'post',
                        ['name' => $trimmed],
                    );
                    if ($res->successful() && ! empty($res->json('id'))) {
                        $ids[] = (int) $res->json('id');
                    }
                } catch (Exception) {
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Map tag names or IDs to valid tag IDs on the WordPress site.
     *
     * @param  array<int, mixed>|string  $tags
     * @return array<int, int>
     */
    private function resolveTagIds(WordPressSite $site, array|string $tags): array
    {
        $cachedTags = collect($site->tags_cache ?? []);
        $tagList = is_array($tags) ? $tags : explode(',', (string) $tags);
        $ids = [];

        foreach ($tagList as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') {
                continue;
            }

            if (is_numeric($trimmed)) {
                $ids[] = (int) $trimmed;

                continue;
            }

            $matched = $cachedTags->first(
                fn ($t) => mb_strtolower($t['name'] ?? '') === mb_strtolower($trimmed)
                    || ($t['slug'] ?? '') === Str::slug($trimmed),
            );

            if ($matched && ! empty($matched['id'])) {
                $ids[] = (int) $matched['id'];
            } else {
                try {
                    $res = $this->wpClient->sendRequest(
                        $site->url,
                        $site->username,
                        (string) $site->application_password,
                        'tags',
                        'post',
                        ['name' => $trimmed],
                    );
                    if ($res->successful() && ! empty($res->json('id'))) {
                        $ids[] = (int) $res->json('id');
                    }
                } catch (Exception) {
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
