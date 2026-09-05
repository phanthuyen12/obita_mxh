<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Support\Facades\Log;

class LinkedInAnalytics
{
    use HasSocialHttpClient;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('trypost.platforms.linkedin.api').'/rest';
    }

    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        if (! $account || ! $postPlatform->platform_post_id) {
            return ['unsupported' => true, 'reason' => 'missing_post_id'];
        }

        if ($account->needsProactiveTokenRefresh()) {
            app(ConnectionVerifier::class)->refreshToken($account);
        }

        $postUrn = rawurlencode($postPlatform->platform_post_id);

        $response = $this->socialHttp()
            ->withToken($account->access_token)
            ->withHeaders([
                'Linkedin-Version' => '202601',
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->get("{$this->baseUrl}/socialActions/{$postUrn}");

        if ($response->failed()) {
            Log::warning('LinkedIn post metrics fetch failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return ['unsupported' => true, 'reason' => 'api_error'];
        }

        $data = $response->json();

        return [
            ['label' => __('analytics.metrics.likes'), 'value' => (int) data_get($data, 'likesSummary.totalLikes', 0)],
            ['label' => __('analytics.metrics.comments'), 'value' => (int) data_get($data, 'commentsSummary.aggregatedTotalComments', 0)],
        ];
    }
}
