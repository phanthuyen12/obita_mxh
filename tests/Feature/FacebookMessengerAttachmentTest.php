<?php

declare(strict_types=1);

use App\Actions\Omnichat\StoreMessage;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Omnichat\ProcessFacebookMessengerWebhook;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['trypost.platforms.facebook.graph_api' => 'https://graph.facebook.com/v25.0']);
    Storage::fake('public');
    Http::preventStrayRequests();
});

it('downloads Facebook webhook attachments and exposes a local media URL', function (string $type, string $mimeType, string $fileName): void {
    Http::fake([
        'https://cdn.example.com/*' => Http::response('attachment bytes', 200, ['content-type' => $mimeType]),
        '*' => Http::response(['error' => ['message' => 'Profile unavailable']], 400),
    ]);

    $account = SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page-123',
        'access_token' => 'page-access-token',
    ]);
    $event = OmnichatWebhookEvent::factory()->create([
        'workspace_id' => $account->workspace_id,
        'social_account_id' => $account->id,
        'payload' => [
            'messaging' => [
                'sender' => ['id' => 'customer-456'],
                'recipient' => ['id' => 'page-123'],
                'timestamp' => 1787112000000,
                'message' => [
                    'mid' => 'mid.image-123',
                    'attachments' => [[
                        'type' => $type,
                        'payload' => ['url' => "https://cdn.example.com/{$fileName}"],
                    ]],
                ],
            ],
        ],
    ]);

    (new ProcessFacebookMessengerWebhook($event))->handle();

    $attachment = OmnichatMessage::query()->sole()->provider_payload['attachments'][0];

    expect($attachment['type'])->toBe($type)
        ->and($attachment['url'])->toContain('/omnichat/facebook/inbound/')
        ->and($attachment['mime_type'])->toBe($mimeType)
        ->and($attachment['size'])->toBe(strlen('attachment bytes'));
})->with([
    ['image', 'image/jpeg', 'image.jpg'],
    ['audio', 'audio/ogg', 'voice.ogg'],
]);

it('sends a Facebook video attachment and stores its display metadata', function (): void {
    Http::fake([
        'https://graph.facebook.com/v25.0/page-123/message_attachments' => Http::response([
            'attachment_id' => 'attachment.video-123',
        ]),
        'https://graph.facebook.com/v25.0/page-123/messages' => Http::response([
            'recipient_id' => 'customer-456',
            'message_id' => 'mid.video-123',
        ]),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
        'platform_user_id' => 'page-123',
        'access_token' => 'page-access-token',
    ]);
    $contact = OmnichatContact::factory()->create(['workspace_id' => $workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
        'external_id' => 'customer-456',
    ]);

    $message = app(StoreMessage::class)->execute(
        $conversation,
        $user,
        '',
        'reply',
        (string) Str::uuid(),
        UploadedFile::fake()->create('demo.mp4', 256, 'video/mp4'),
    );

    expect($message->type)->toBe('video')
        ->and($message->external_id)->toBe('mid.video-123')
        ->and($message->provider_payload['attachments'][0]['type'])->toBe('video')
        ->and($message->provider_payload['attachments'][0]['url'])->toContain('/omnichat/facebook/');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://graph.facebook.com/v25.0/page-123/message_attachments'
            && str_contains($request->body(), '"type":"video"');
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://graph.facebook.com/v25.0/page-123/messages'
            && data_get($request->data(), 'message.attachment.payload.attachment_id') === 'attachment.video-123';
    });
});
