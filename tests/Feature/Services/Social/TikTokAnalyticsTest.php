<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Post\PostMetricsFetcher;
use App\Services\Social\TikTokAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'access_token' => 'tiktok-access-token',
        'scopes' => ['video.publish', 'video.list'],
    ]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);
});

test('tiktok analytics fetches post metrics from video query api', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.tiktokapis.com/v2/video/query/*' => Http::response([
            'data' => [
                'videos' => [[
                    'id' => '7420123456789012345',
                    'view_count' => 1200,
                    'like_count' => 130,
                    'comment_count' => 12,
                    'share_count' => 7,
                ]],
            ],
            'error' => ['code' => 'ok', 'message' => 'ok'],
        ]),
    ]);

    $postPlatform = PostPlatform::factory()->tiktok()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => '7420123456789012345',
    ]);

    $metrics = app(TikTokAnalytics::class)->fetchPostMetrics($postPlatform);

    expect($metrics)->toMatchArray([
        ['label' => 'Views', 'value' => 1200],
        ['label' => 'Likes', 'value' => 130],
        ['label' => 'Comments', 'value' => 12],
        ['label' => 'Shares', 'value' => 7],
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/video/query/')
        && $request->hasHeader('Authorization', 'Bearer tiktok-access-token')
        && $request['filters']['video_ids'] === ['7420123456789012345']);
});

test('post metrics fetcher supports tiktok platforms', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.tiktokapis.com/v2/video/query/*' => Http::response([
            'data' => [
                'videos' => [[
                    'id' => '7420123456789012345',
                    'view_count' => 200,
                    'like_count' => 20,
                    'comment_count' => 2,
                    'share_count' => 1,
                ]],
            ],
            'error' => ['code' => 'ok', 'message' => 'ok'],
        ]),
    ]);

    $postPlatform = PostPlatform::factory()->tiktok()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => '7420123456789012345',
    ]);

    $metrics = app(PostMetricsFetcher::class)->forPlatform($postPlatform);

    expect($metrics)->not->toHaveKey('unsupported')
        ->and($metrics[0])->toMatchArray(['label' => 'Views', 'value' => 200]);
});

test('tiktok analytics returns unsupported when video query fails', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://open.tiktokapis.com/v2/video/query/*' => Http::response([
            'error' => ['code' => 'scope_not_authorized'],
        ], 403),
    ]);

    $postPlatform = PostPlatform::factory()->tiktok()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => 'pub-queued-id',
    ]);

    expect(app(TikTokAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toMatchArray(['unsupported' => true, 'reason' => 'api_error']);
});
