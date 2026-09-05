<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Support\Omnichat\ZaloOaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['trypost.platforms.zalo-oa.api' => 'https://openapi.zalo.me']);
    config(['trypost.platforms.zalo-oa.oauth_api' => 'https://oauth.zaloapp.com']);
    config(['services.zalo-oa.client_id' => 'app-id']);
    config(['services.zalo-oa.client_secret' => 'secret-key']);
    Http::preventStrayRequests();
});

it('exchanges the Zalo authorization code and loads the OA profile', function (): void {
    Http::fake([
        'https://oauth.zaloapp.com/v4/oa/access_token' => Http::response([
            'access_token' => 'oa-access-token',
            'refresh_token' => 'oa-refresh-token',
            'expires_in' => 90000,
            'error' => 0,
        ]),
        'https://openapi.zalo.me/v2.0/oa/getoa' => Http::response([
            'data' => ['oa_id' => 'oa-id', 'name' => 'Test OA'],
            'error' => 0,
        ]),
    ]);

    $client = app(ZaloOaClient::class);
    $tokens = $client->exchangeAuthorizationCode('authorization-code', 'verifier');
    $profile = $client->oaProfile($tokens['access_token']);

    expect($profile['data']['oa_id'])->toBe('oa-id');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://oauth.zaloapp.com/v4/oa/access_token'
            && $request->header('secret_key') === ['secret-key']
            && $request->data()['code_verifier'] === 'verifier';
    });
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openapi.zalo.me/v2.0/oa/getoa');
});

it('loads a Zalo user profile', function (): void {
    Http::fake([
        'https://openapi.zalo.me/v3.0/oa/user/detail*' => Http::response([
            'data' => [
                'user_id' => '8038294108466906848',
                'user_id_by_app' => '2831414034404527652',
                'display_name' => 'Phan Thuyên',
                'avatar' => 'https://example.test/avatar.jpg',
            ],
            'error' => 0,
            'message' => 'Success',
        ]),
    ]);

    $profile = app(ZaloOaClient::class)->userProfile('access-token', '8038294108466906848');

    expect(data_get($profile, 'data.display_name'))->toBe('Phan Thuyên')
        ->and(data_get($profile, 'data.avatar'))->toBe('https://example.test/avatar.jpg');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://openapi.zalo.me/v3.0/oa/user/detail')
            && $request->header('access_token') === ['access-token'];
    });
});

it('throws when Zalo returns an application error', function (): void {
    Http::fake([
        'https://openapi.zalo.me/v3.0/oa/user/detail*' => Http::response([
            'error' => -124,
            'message' => 'Invalid user id',
        ]),
    ]);

    expect(fn (): array => app(ZaloOaClient::class)->userProfile('access-token', 'invalid'))
        ->toThrow(RuntimeException::class, 'Zalo OA profile lookup failed: Invalid user id');
});

it('sends image, file, sticker, and reaction payloads using the documented endpoints', function (): void {
    $account = new SocialAccount(['access_token' => 'access-token']);
    Storage::fake('public');

    Http::fake([
        'https://openapi.zalo.me/v2.0/oa/upload/image' => Http::response([
            'data' => ['attachment_id' => 'uploaded-attachment'],
            'error' => 0,
        ]),
        'https://openapi.zalo.me/v3.0/oa/message/cs' => Http::response([
            'data' => ['message_id' => 'sent-message'],
            'error' => 0,
        ]),
        'https://openapi.zalo.me/v2.0/oa/message' => Http::response(['error' => 0]),
    ]);

    $client = app(ZaloOaClient::class);
    $imageResult = $client->sendImage(
        $account,
        'user-id',
        UploadedFile::fake()->image('images.jpeg', 10, 10),
        'Caption',
    );
    $client->sendFile($account, 'user-id', 'file-token');
    $client->sendSticker($account, 'user-id', 'sticker-id');
    $client->react($account, 'user-id', 'message-id', '/-strong');

    expect($imageResult['attachment']['id'])->toBe('uploaded-attachment');

    Http::assertSentCount(5);
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://openapi.zalo.me/v3.0/oa/message/cs'
            && data_get($request->data(), 'message.attachment.type') === 'template'
            && data_get($request->data(), 'message.attachment.payload.template_type') === 'media'
            && data_get($request->data(), 'message.attachment.payload.elements.0.media_type') === 'image'
            && data_get($request->data(), 'message.attachment.payload.elements.0.attachment_id') === 'uploaded-attachment';
    });
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://openapi.zalo.me/v2.0/oa/upload/image'
            && $request->header('access_token') === ['access-token']
            && str_contains((string) $request->header('content-type')[0], 'multipart/form-data')
            && $request->header('content-type') !== ['application/json'];
    });
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://openapi.zalo.me/v2.0/oa/message'
            && data_get($request->data(), 'sender_action.react_message_id') === 'message-id'
            && data_get($request->data(), 'recipient.user_id') === 'user-id';
    });
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://openapi.zalo.me/v3.0/oa/message/cs'
            && data_get($request->data(), 'message.attachment.type') === 'template'
            && data_get($request->data(), 'message.attachment.payload.template_type') === 'media'
            && data_get($request->data(), 'message.attachment.payload.elements.0.media_type') === 'sticker'
            && data_get($request->data(), 'message.attachment.payload.elements.0.attachment_id') === 'sticker-id';
    });
});

it('does not send an empty image to Zalo', function (): void {
    $account = new SocialAccount(['access_token' => 'access-token']);
    $image = UploadedFile::fake()->createWithContent('images.jpeg', '', 'image/jpeg');

    expect(fn (): array => app(ZaloOaClient::class)->sendImage($account, 'user-id', $image, null))
        ->toThrow(RuntimeException::class, 'Zalo OA image upload is empty or invalid.');

    Http::assertNothingSent();
});
