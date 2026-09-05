<?php

declare(strict_types=1);

use App\Actions\Omnichat\StoreMessage;
use App\Enums\SocialAccount\Platform;
use App\Jobs\Omnichat\ProcessShopeeWebhook;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Omnichat\ShopeeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.shopee.partner_id' => '10001',
        'services.shopee.partner_key' => 'partner-secret',
        'services.shopee.redirect' => 'https://example.test/shopee/callback',
        'trypost.platforms.shopee.api' => 'https://partner.shopeemobile.com',
        'trypost.platforms.shopee.auth_api' => 'https://partner.shopeemobile.com',
    ]);
    Http::preventStrayRequests();
});

it('signs authenticated Shopee requests exactly like the source implementation', function (): void {
    $signature = app(ShopeeClient::class)->signature('/api/v2/sellerchat/get_message', 1700000000, 'access-token', '90001');

    expect($signature)->toBe(hash_hmac('sha256', '10001/api/v2/sellerchat/get_message1700000000access-token90001', 'partner-secret'));
    parse_str((string) parse_url(app(ShopeeClient::class)->authorizationUrl('oauth-state'), PHP_URL_QUERY), $authorizationQuery);

    expect(data_get($authorizationQuery, 'redirect'))->toBe('https://example.test/shopee/callback');
});

it('reads the Shopee token response', function (): void {
    Http::fake([
        'https://partner.shopeemobile.com/api/v2/auth/token/get*' => Http::response([
            'error' => '',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expire_in' => 14400,
        ]),
    ]);

    expect(app(ShopeeClient::class)->obtainAccessToken('authorization-code', '90001'))
        ->toMatchArray(['access_token' => 'access-token', 'refresh_token' => 'refresh-token']);
});

it('supports a wrapped Shopee token response', function (): void {
    Http::fake([
        'https://partner.shopeemobile.com/api/v2/auth/token/get*' => Http::response([
            'error' => '',
            'response' => ['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expire_in' => 14400],
        ]),
    ]);

    expect(app(ShopeeClient::class)->obtainAccessToken('authorization-code', '90001'))
        ->toMatchArray(['access_token' => 'access-token', 'refresh_token' => 'refresh-token']);
});

it('queues a Shopee webhook once and imports its message', function (): void {
    Queue::fake();
    $account = SocialAccount::factory()->create([
        'platform' => Platform::Shopee, 'platform_user_id' => '90001',
        'access_token' => 'access-token', 'token_expires_at' => now()->addHour(),
    ]);
    $payload = shopeeMessagePayload();

    $this->postJson(route('shopee.webhook'), $payload)->assertOk();
    $this->postJson(route('shopee.webhook'), $payload)->assertOk();

    expect(OmnichatWebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessShopeeWebhook::class, 1);

    app()->call([new ProcessShopeeWebhook(OmnichatWebhookEvent::query()->sole()), 'handle']);

    expect(OmnichatContact::query()->sole()->display_name)->toBe('Nguyen Buyer')
        ->and(OmnichatConversation::query()->sole()->external_id)->toBe('conversation-123')
        ->and(OmnichatMessage::query()->sole()->external_id)->toBe('message-123')
        ->and(OmnichatMessage::query()->sole()->body)->toBe('Xin chào shop')
        ->and(OmnichatMessage::query()->sole()->direction)->toBe('inbound')
        ->and($account->fresh())->not->toBeNull();
});

it('sends a Shopee reply and stores the returned message id', function (): void {
    Http::fake([
        'https://partner.shopeemobile.com/api/v2/sellerchat/send_message*' => Http::response([
            'error' => '', 'response' => ['message_id' => 'sent-123'],
        ]),
    ]);
    $account = SocialAccount::factory()->create([
        'platform' => Platform::Shopee, 'platform_user_id' => '90001',
        'access_token' => 'access-token', 'token_expires_at' => now()->addHour(),
    ]);
    $contact = OmnichatContact::factory()->create(['workspace_id' => $account->workspace_id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $account->workspace_id, 'social_account_id' => $account->id,
        'contact_id' => $contact->id, 'external_id' => 'conversation-123',
        'meta' => ['shopee_recipient_id' => '80001', 'business_type' => 0],
    ]);
    $user = User::factory()->create(['account_id' => $account->workspace->account_id]);

    $message = app(StoreMessage::class)->execute($conversation, $user, 'Chào bạn', 'reply', (string) Str::uuid());

    expect($message->external_id)->toBe('sent-123')->and($message->status)->toBe('sent');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data()['to_id'] === 80001
        && data_get($request->data(), 'content.text') === 'Chào bạn');
});

/** @return array<string, mixed> */
function shopeeMessagePayload(): array
{
    return [
        'shop_id' => 90001,
        'data' => ['content' => [
            'type' => 'message', 'message_id' => 'message-123',
            'conversation_id' => 'conversation-123', 'from_id' => 80001,
            'from_shop_id' => 0, 'to_id' => 90001, 'to_shop_id' => 90001,
            'from_user_name' => 'Nguyen Buyer', 'created_timestamp' => 1787112000,
            'message_type' => 'text', 'content' => ['text' => 'Xin chào shop'],
        ]],
    ];
}
