<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FacebookAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl;

    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.facebook.graph_api');
    }

    public function getMetrics(SocialAccount $account, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $since ??= now()->subDays(7);
        $until ??= now();

        $cacheKey = "analytics:facebook:{$account->id}:{$since->format('Y-m-d')}:{$until->format('Y-m-d')}";
        $cacheTtl = app()->isProduction() ? 3600 : 1;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($account, $since, $until) {
            return $this->fetchMetricsFromApi($account, $since, $until);
        });
    }

    public function fetchPostMetrics(PostPlatform $postPlatform, ?array $feedData = null): array
    {
        $postPlatform->loadMissing('socialAccount');
        $account = $postPlatform->socialAccount;

        if (! $account || ! $postPlatform->platform_post_id) {
            return ['unsupported' => true, 'reason' => 'missing_post_id'];
        }

        $platformPostId = $postPlatform->platform_post_id;
        if (! str_contains((string) $platformPostId, '_')) {
            $platformPostId = $account->platform_user_id.'_'.$platformPostId;
        }

        $client = $this->socialHttp();

        $response = $client
            ->get("{$this->baseUrl}/{$platformPostId}/insights", [
                'metric' => 'post_total_media_view_unique,post_media_view',
                'period' => 'lifetime',
                'access_token' => $account->access_token,
            ]);

        if ($response->failed()) {
            $err = data_get($response->json(), 'error.message') ?? $response->body();
            Log::warning("Facebook post metrics fetch failed for post {$platformPostId}: {$err}");
        }

        $insights = data_get($response->json(), 'data', []);
        $insightsMap = collect($insights)->keyBy('name');

        $reach = (int) data_get($insightsMap->get('post_total_media_view_unique'), 'values.0.value', 0);
        $impressions = (int) (data_get($insightsMap->get('post_media_view'), 'values.0.value') ?? $reach);

        $reactions = 0;
        $comments = 0;
        $shares = 0;

        if ($feedData !== null) {
            $shares = (int) data_get($feedData, 'shares.count', 0);
            $comments = (int) (data_get($feedData, 'comments.summary.total_count') ?? data_get($feedData, 'comments.count', 0));
            $reactions = (int) (data_get($feedData, 'reactions.summary.total_count') ?? data_get($feedData, 'likes.summary.total_count') ?? 0);
        } else {
            $postResponse = $client->get("{$this->baseUrl}/{$platformPostId}", [
                'fields' => 'shares,comments.summary(true).filter(stream).limit(0),reactions.summary(true).limit(0),likes.summary(true).limit(0)',
                'access_token' => $account->access_token,
            ]);

            if ($postResponse->successful()) {
                $postData = $postResponse->json();
                $shares = (int) data_get($postData, 'shares.count', 0);
                $comments = (int) (data_get($postData, 'comments.summary.total_count') ?? data_get($postData, 'comments.count', 0));
                $reactions = (int) (data_get($postData, 'reactions.summary.total_count') ?? data_get($postData, 'likes.summary.total_count') ?? data_get($postData, 'reactions.count', 0));
            }
        }

        return [
            ['key' => 'reach', 'label' => 'Reach', 'value' => $reach],
            ['key' => 'impressions', 'label' => 'Impressions', 'value' => $impressions],
            ['key' => 'reactions', 'label' => 'Reactions', 'value' => $reactions],
            ['key' => 'comments', 'label' => 'Comments', 'value' => $comments],
            ['key' => 'shares', 'label' => 'Shares', 'value' => $shares],
        ];
    }

    private function fetchMetricsFromApi(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $this->accessToken = $account->access_token;

        $response = $this->getHttpClient()
            ->get("{$this->baseUrl}/{$account->platform_user_id}/insights", [
                'metric' => 'page_total_media_view_unique,post_total_media_view_unique,page_post_engagements,page_daily_follows,page_media_view',
                'period' => 'day',
                'since' => $since->startOfDay()->unix(),
                'until' => $until->endOfDay()->unix(),
                'access_token' => $this->accessToken,
            ]);

        if ($response->failed()) {
            Log::warning('Facebook page insights fetch failed', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return [];
        }

        $data = data_get($response->json(), 'data', []);
        $metrics = [];

        foreach ($data as $metric) {
            $name = data_get($metric, 'name');
            $values = data_get($metric, 'values', []);

            if (empty($values)) {
                continue;
            }

            $total = collect($values)->sum('value');

            $label = match ($name) {
                'page_total_media_view_unique' => __('analytics.metrics.page_reach'),
                'post_total_media_view_unique' => __('analytics.metrics.posts_reach'),
                'page_post_engagements' => __('analytics.metrics.posts_engagement'),
                'page_daily_follows' => __('analytics.metrics.page_followers'),
                'page_media_view' => __('analytics.metrics.page_views'),
                default => ucfirst(str_replace('_', ' ', $name)),
            };

            $metrics[] = ['label' => $label, 'value' => $total];
        }

        return $metrics;
    }

    private function getHttpClient(): PendingRequest
    {
        return $this->socialHttp();
    }
}
