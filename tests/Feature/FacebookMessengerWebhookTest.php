<?php

declare(strict_types=1);

use App\Jobs\Omnichat\ProcessFacebookMessengerWebhook;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config([
        'services.facebook.client_secret' => 'facebook-app-secret',
        'services.facebook.webhook_verify_token' => 'facebook-verify-token',
        'trypost.platforms.facebook.graph_api' => 'https://graph.facebook.com/v25.0',
    ]);

    Http::preventStrayRequests();
});

it('verifies the callback URL with the configured token', function (): void {
    $this->get(route('facebook.messenger.webhook.verify', [
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'facebook-verify-token',
        'hub.challenge' => 'challenge-123',
    ]))
        ->assertOk()
        ->assertSeeText('challenge-123');
});

it('rejects callback URL verification with the wrong token', function (): void {
    $this->get(route('facebook.messenger.webhook.verify', [
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'wrong-token',
        'hub.challenge' => 'challenge-123',
    ]))->assertForbidden();
});

it('rejects event notifications with an invalid signature', function (): void {
    facebookMessengerRequest(facebookMessengerPayload(), 'sha256=invalid')
        ->assertForbidden();

    expect(OmnichatWebhookEvent::query()->count())->toBe(0);
});

it('stores and queues a valid Facebook Messenger event once', function (): void {
    Queue::fake();

    $socialAccount = SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page-123',
    ]);
    $payload = facebookMessengerPayload();

    facebookMessengerRequest($payload)->assertOk();
    facebookMessengerRequest($payload)->assertOk();

    $event = OmnichatWebhookEvent::query()->sole();

    expect($event->workspace_id)->toBe($socialAccount->workspace_id)
        ->and($event->social_account_id)->toBe($socialAccount->id)
        ->and($event->external_event_id)->toBe('page-123:mid.123')
        ->and($event->status)->toBe('pending');

    Queue::assertPushed(ProcessFacebookMessengerWebhook::class, 1);
});

it('turns an inbound Facebook message into a contact conversation and message', function (): void {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Profile unavailable']], 400),
    ]);

    $socialAccount = SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page-123',
    ]);

    facebookMessengerRequest(facebookMessengerPayload())->assertOk();

    $event = OmnichatWebhookEvent::query()->sole();

    (new ProcessFacebookMessengerWebhook($event))->handle();

    $contact = OmnichatContact::query()->sole();
    $conversation = OmnichatConversation::query()->sole();
    $message = OmnichatMessage::query()->sole();

    expect($contact->workspace_id)->toBe($socialAccount->workspace_id)
        ->and($contact->identities()->sole()->external_id)->toBe('customer-456')
        ->and($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->external_id)->toBe('customer-456')
        ->and($conversation->last_message_preview)->toBe('Xin chào')
        ->and($message->external_id)->toBe('mid.123')
        ->and($message->direction)->toBe('inbound')
        ->and($message->body)->toBe('Xin chào')
        ->and($event->fresh()->status)->toBe('processed');
});

it('fetches the Facebook profile for a new conversation', function (): void {
    Queue::fake();

    Http::fake([
        'https://graph.facebook.com/v25.0/customer-456*' => Http::response([
            'first_name' => 'Nguyễn',
            'last_name' => 'An',
            'profile_pic' => 'https://example.com/facebook-avatar.jpg',
        ]),
    ]);

    SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page-123',
        'access_token' => 'page-access-token',
    ]);

    facebookMessengerRequest(facebookMessengerPayload())->assertOk();

    (new ProcessFacebookMessengerWebhook(OmnichatWebhookEvent::query()->sole()))->handle();

    $contact = OmnichatContact::query()->sole();

    $identity = $contact->identities()->sole();

    expect($contact->display_name)->toBe('Nguyễn An')
        ->and($contact->avatar_url)->toBe('https://example.com/facebook-avatar.jpg')
        ->and($identity->display_name)->toBe('Nguyễn An')
        ->and($identity->avatar_url)->toBe('https://example.com/facebook-avatar.jpg');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/customer-456?fields=first_name%2Clast_name%2Cprofile_pic&access_token=page-access-token');
});

it('falls back to conversation participants when the Facebook profile is unavailable', function (): void {
    Queue::fake();

    Http::fake([
        'https://graph.facebook.com/v25.0/customer-456*' => Http::response([
            'error' => ['code' => 100, 'error_subcode' => 33],
        ], 400),
        'https://graph.facebook.com/v25.0/page-123/conversations*' => Http::response([
            'data' => [[
                'participants' => [
                    'data' => [
                        ['id' => 'customer-456', 'name' => 'Nguyễn An'],
                        ['id' => 'page-123', 'name' => 'Trang Facebook'],
                    ],
                ],
            ]],
        ]),
    ]);

    SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page-123',
        'access_token' => 'page-access-token',
    ]);

    facebookMessengerRequest(facebookMessengerPayload())->assertOk();

    (new ProcessFacebookMessengerWebhook(OmnichatWebhookEvent::query()->sole()))->handle();

    $contact = OmnichatContact::query()->sole();

    expect($contact->display_name)->toBe('Nguyễn An')
        ->and($contact->identities()->sole()->display_name)->toBe('Nguyễn An');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_starts_with(
        $request->url(),
        'https://graph.facebook.com/v25.0/page-123/conversations?',
    ));
});

/** @return array<string, mixed> */
function facebookMessengerPayload(): array
{
    return [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'page-123',
                'time' => 1787112000000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'customer-456'],
                        'recipient' => ['id' => 'page-123'],
                        'timestamp' => 1787112000000,
                        'message' => [
                            'mid' => 'mid.123',
                            'text' => 'Xin chào',
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/** @param array<string, mixed> $payload */
function facebookMessengerRequest(array $payload, ?string $signature = null): TestResponse
{
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature ??= 'sha256='.hash_hmac(
        'sha256',
        $content,
        (string) config('services.facebook.client_secret'),
    );

    return test()->call(
        'POST',
        route('facebook.messenger.webhook'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ],
        $content,
    );
}
