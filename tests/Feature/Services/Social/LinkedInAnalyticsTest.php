<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Post\PostMetricsFetcher;
use App\Services\Social\LinkedInAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->account = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'access_token' => 'linkedin-access-token',
    ]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);
});

test('linkedin analytics fetches personal post metrics from social actions api', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.linkedin.com/rest/socialActions/*' => Http::response([
            'likesSummary' => ['totalLikes' => 42],
            'commentsSummary' => ['aggregatedTotalComments' => 7],
        ]),
    ]);

    $postPlatform = PostPlatform::factory()->linkedin()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => 'urn:li:share:1234567890',
    ]);

    expect(app(LinkedInAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toBe([
            ['label' => 'Likes', 'value' => 42],
            ['label' => 'Comments', 'value' => 7],
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/socialActions/urn%3Ali%3Ashare%3A1234567890')
        && $request->hasHeader('Authorization', 'Bearer linkedin-access-token')
        && $request->hasHeader('Linkedin-Version', '202601')
        && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0'));
});

test('post metrics fetcher supports linkedin personal platforms', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.linkedin.com/rest/socialActions/*' => Http::response([
            'likesSummary' => ['totalLikes' => 9],
            'commentsSummary' => ['aggregatedTotalComments' => 3],
        ]),
    ]);

    $postPlatform = PostPlatform::factory()->linkedin()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => 'urn:li:share:9876543210',
    ]);

    $metrics = app(PostMetricsFetcher::class)->forPlatform($postPlatform);

    expect($metrics)->not->toHaveKey('unsupported')
        ->and($metrics)->toBe([
            ['label' => 'Likes', 'value' => 9],
            ['label' => 'Comments', 'value' => 3],
        ]);
});

test('linkedin analytics returns unsupported when social actions api fails', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.linkedin.com/rest/socialActions/*' => Http::response(['message' => 'forbidden'], 403),
    ]);

    $postPlatform = PostPlatform::factory()->linkedin()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->account->id,
        'platform_post_id' => 'urn:li:share:1234567890',
    ]);

    expect(app(LinkedInAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toMatchArray(['unsupported' => true, 'reason' => 'api_error']);
});
