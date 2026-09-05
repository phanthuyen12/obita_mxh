<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Jobs\SubscribeFacebookPageToWebhooks;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it subscribes a Facebook Page to Messenger webhook fields', function () {
    $account = SocialAccount::factory()->create([
        'platform' => Platform::Facebook,
        'platform_user_id' => '263218426865326',
        'access_token' => 'page-access-token',
        'meta' => [],
    ]);

    Http::fake([
        'https://graph.facebook.com/*/263218426865326/subscribed_apps' => Http::response(['success' => true]),
    ]);

    (new SubscribeFacebookPageToWebhooks((string) $account->id))->handle();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['subscribed_fields'] === 'messages,messaging_postbacks'
        && $request['access_token'] === 'page-access-token');

    expect(data_get($account->fresh()->meta, 'webhook_subscription.status'))->toBe('subscribed');
});

test('it records a non-retriable Meta rejection', function () {
    $account = SocialAccount::factory()->create([
        'platform' => Platform::Facebook,
        'access_token' => 'page-access-token',
        'meta' => [],
    ]);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Missing pages_manage_metadata', 'code' => 200, 'type' => 'OAuthException'],
        ], 400),
    ]);

    (new SubscribeFacebookPageToWebhooks((string) $account->id))->handle();

    $subscription = data_get($account->fresh()->meta, 'webhook_subscription');

    expect($subscription)
        ->status->toBe('failed')
        ->error->toBe('Missing pages_manage_metadata');
});
