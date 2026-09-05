<?php

declare(strict_types=1);

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatChannel;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Models\OmnichatWebchatSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function websiteChannel(string $origin = 'https://shop.example.com'): OmnichatChannel
{
    $publicKey = 'wc_pk_'.Str::random(48);

    return OmnichatChannel::factory()->website()->create([
        'access_token' => $publicKey,
        'public_key_hash' => hash('sha256', $publicKey),
        'settings' => [
            'authorized_origins' => [$origin],
            'welcome_message' => 'Xin chào!',
            'offline_message' => 'Ngoài giờ.',
            'primary_color' => '#2563EB',
            'position' => 'right',
        ],
    ]);
}

it('returns public widget config only for an allowed origin', function (): void {
    $channel = websiteChannel();

    $this->withHeader('Origin', 'https://shop.example.com')
        ->getJson(route('api.website-chat.config', ['public_key' => $channel->access_token]))
        ->assertOk()
        ->assertJsonPath('channel.id', $channel->id)
        ->assertJsonPath('branding.welcome_message', 'Xin chào!');

    $this->withHeader('Origin', 'https://evil.example')
        ->getJson(route('api.website-chat.config', ['public_key' => $channel->access_token]))
        ->assertForbidden();
});

it('uses the referrer origin when a same-origin get request omits the origin header', function (): void {
    $channel = websiteChannel();

    $this->withHeader('Referer', 'https://shop.example.com/products/coffee?size=large')
        ->getJson(route('api.website-chat.config', ['public_key' => $channel->access_token]))
        ->assertOk()
        ->assertJsonPath('channel.id', $channel->id);

    $this->flushHeaders()
        ->getJson(route('api.website-chat.config', ['public_key' => $channel->access_token]))
        ->assertForbidden();
});

it('accepts an explicit authorized origin from a partner backend', function (): void {
    $channel = websiteChannel();

    $this->withHeader('X-Website-Chat-Origin', 'https://shop.example.com')
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => (string) Str::uuid(),
            'email' => 'partner@example.com',
            'support_request' => 'Tôi cần được hỗ trợ.',
        ])
        ->assertCreated();
});

it('creates an isolated webchat session and stores inbound messages idempotently', function (): void {
    $channel = websiteChannel();
    $origin = 'https://shop.example.com';
    $visitorId = (string) Str::uuid();
    $tag = OmnichatTag::factory()->create([
        'workspace_id' => $channel->workspace_id,
        'name' => 'Khách VIP',
    ]);

    $sessionResponse = $this->withHeader('Origin', $origin)
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => $visitorId,
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'support_request' => 'Tôi muốn được tư vấn sản phẩm.',
            'locale' => 'vi',
            'context' => [
                'page_url' => 'https://shop.example.com/products/coffee',
                'tags' => ['Khách VIP'],
            ],
        ])->assertCreated();

    $token = $sessionResponse->json('token');
    $clientId = (string) Str::uuid();
    Event::fake([OmnichatMessageCreated::class]);

    $payload = [
        'client_id' => $clientId,
        'type' => 'image',
        'body' => 'Tôi cần tư vấn cà phê',
        'attachments' => [[
            'id' => 'partner-file-001',
            'type' => 'image',
            'url' => 'https://cdn.example.com/coffee.jpg',
            'file_name' => 'coffee.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
        ]],
        'metadata' => ['order_id' => 'ORDER-1001'],
    ];
    $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer '.$token])
        ->postJson(route('api.website-chat.messages.store'), $payload)->assertCreated();
    $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer '.$token])
        ->postJson(route('api.website-chat.messages.store'), $payload)->assertCreated();

    $session = OmnichatWebchatSession::query()->sole();
    $conversation = OmnichatConversation::query()->sole();
    $message = OmnichatMessage::query()->where('client_id', $clientId)->sole();

    expect($session->channel_id)->toBe($channel->id)
        ->and($conversation->channel_id)->toBe($channel->id)
        ->and($conversation->tags()->sole()->is($tag))->toBeTrue()
        ->and($conversation->social_account_id)->toBeNull()
        ->and($message->body)->toBe('Tôi cần tư vấn cà phê')
        ->and($message->type)->toBe('image')
        ->and(data_get($message->provider_payload, 'attachments.0.file_name'))->toBe('coffee.jpg')
        ->and(data_get($message->provider_payload, 'metadata.order_id'))->toBe('ORDER-1001')
        ->and($message->direction)->toBe('inbound')
        ->and($message->channel_id)->toBe($channel->id);
    Event::assertDispatched(OmnichatMessageCreated::class, 1);

    $this->withHeaders(['Origin' => $origin, 'Authorization' => 'Bearer '.$token])
        ->getJson(route('api.website-chat.messages.index'))
        ->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.1.body', 'Tôi cần tư vấn cà phê')
        ->assertJsonPath('messages.1.attachments.0.file_name', 'coffee.jpg')
        ->assertJsonPath('messages.1.metadata.order_id', 'ORDER-1001');
});

it('requires visitor details before creating a profile and starts with their support request', function (): void {
    $channel = websiteChannel();

    $this->withHeader('Origin', 'https://shop.example.com')
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => (string) Str::uuid(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'support_request']);

    $this->withHeader('Origin', 'https://shop.example.com')
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => (string) Str::uuid(),
            'email' => 'visitor@example.com',
            'support_request' => 'Tôi cần hỗ trợ đơn hàng 123.',
        ])
        ->assertCreated();

    $conversation = OmnichatConversation::query()->sole();

    expect($conversation->contact->email)->toBe('visitor@example.com')
        ->and($conversation->messages()->sole()->body)->toBe('Tôi cần hỗ trợ đơn hàng 123.')
        ->and($conversation->meta['unread_count'])->toBe(1);
});

it('accepts website chat image uploads up to five megabytes', function (): void {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
    $channel = websiteChannel();
    $sessionResponse = $this->withHeader('Origin', 'https://shop.example.com')
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => (string) Str::uuid(),
            'email' => 'image@example.com',
            'support_request' => 'Tôi sẽ gửi ảnh lỗi.',
        ])->assertCreated();

    $headers = [
        'Accept' => 'application/json',
        'Origin' => 'https://shop.example.com',
        'Authorization' => 'Bearer '.$sessionResponse->json('token'),
    ];

    $response = $this->withHeaders($headers)->post(route('api.website-chat.messages.store'), [
        'client_id' => (string) Str::uuid(),
        'image' => UploadedFile::fake()->image('loi-san-pham.jpg')->size(5120),
    ])->assertCreated()->assertJsonPath('message.type', 'image');

    expect($response->json('message.attachments.0.file_name'))->toBe('loi-san-pham.jpg');
    Storage::disk('public')->assertExists(
        str_replace('/storage/', '', parse_url($response->json('message.attachments.0.url'), PHP_URL_PATH)),
    );

    $this->withHeaders($headers)->post(route('api.website-chat.messages.store'), [
        'client_id' => (string) Str::uuid(),
        'image' => UploadedFile::fake()->image('qua-lon.jpg')->size(5121),
    ])->assertUnprocessable()->assertInvalid('image');
});

it('rejects a session token used from a different origin', function (): void {
    $channel = websiteChannel();
    $response = $this->withHeader('Origin', 'https://shop.example.com')
        ->postJson(route('api.website-chat.sessions.store', ['public_key' => $channel->access_token]), [
            'visitor_id' => (string) Str::uuid(),
            'email' => 'origin@example.com',
            'support_request' => 'Tôi cần hỗ trợ.',
        ])->assertCreated();

    $this->withHeaders([
        'Origin' => 'https://evil.example',
        'Authorization' => 'Bearer '.$response->json('token'),
    ])->getJson(route('api.website-chat.messages.index'))->assertUnauthorized();
});
