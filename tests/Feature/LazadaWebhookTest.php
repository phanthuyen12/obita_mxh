<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Jobs\Omnichat\ProcessLazadaWebhook;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['services.lazada.app_key' => 'lazada-key', 'services.lazada.app_secret' => 'lazada-secret']);
});

it('rejects a Lazada push with an invalid signature', function (): void {
    lazadaWebhookRequest(lazadaMessagePayload(), 'invalid')->assertForbidden();
    expect(OmnichatWebhookEvent::query()->count())->toBe(0);
});

it('queues a Lazada IM push idempotently', function (): void {
    Queue::fake();
    SocialAccount::factory()->create(['platform' => Platform::Lazada, 'platform_user_id' => 'seller-123']);

    lazadaWebhookRequest(lazadaMessagePayload())->assertOk();
    lazadaWebhookRequest(lazadaMessagePayload())->assertOk();

    expect(OmnichatWebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessLazadaWebhook::class, 1);
});

it('stores an inbound Lazada text message', function (): void {
    Queue::fake();
    SocialAccount::factory()->create(['platform' => Platform::Lazada, 'platform_user_id' => 'seller-123']);
    lazadaWebhookRequest(lazadaMessagePayload())->assertOk();

    app()->call([new ProcessLazadaWebhook(OmnichatWebhookEvent::query()->sole()), 'handle']);

    $conversation = OmnichatConversation::query()->sole();
    $message = OmnichatMessage::query()->sole();
    expect($conversation->external_id)->toBe('session-123')
        ->and($message->external_id)->toBe('message-123')
        ->and($message->direction)->toBe('inbound')
        ->and($message->body)->toBe('Xin chào shop');
});

/** @return array<string, mixed> */
function lazadaMessagePayload(): array
{
    return [
        'seller_id' => 'seller-123',
        'message_type' => 2,
        'timestamp' => 1787112000000,
        'data' => [
            'session_id' => 'session-123', 'message_id' => 'message-123',
            'content' => json_encode(['txt' => 'Xin chào shop'], JSON_THROW_ON_ERROR),
            'from_user_id' => 'buyer-456', 'from_account_type' => 1,
            'to_account_id' => 'seller-123', 'to_account_type' => 2,
            'send_time' => 1787112000000, 'template_id' => 1, 'site_id' => 'VN',
        ],
    ];
}

/** @param array<string, mixed> $payload */
function lazadaWebhookRequest(array $payload, ?string $signature = null): TestResponse
{
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature ??= hash_hmac('sha256', 'lazada-key'.$content, 'lazada-secret');

    return test()->call('POST', route('lazada.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => $signature,
    ], $content);
}
