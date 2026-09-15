<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Omnichat\StoreMessage;
use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Omnichat\ProcessTelegramOmnichatWebhook;
use App\Models\OmnichatChannel;
use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function telegramAdminUser(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $user->account_id, 'user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

it('lets a workspace admin connect a telegram bot channel', function (): void {
    [$user, $workspace] = telegramAdminUser();

    Http::fake([
        'https://api.telegram.org/bot123456:fake-token/getMe' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 987654321,
                'is_bot' => true,
                'first_name' => 'King Coffee Bot',
                'username' => 'king_coffee_bot',
            ],
        ]),
        'https://api.telegram.org/bot123456:fake-token/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
        ]),
        'https://api.telegram.org/bot123456:fake-token/getUserProfilePhotos*' => Http::response([
            'ok' => true,
            'result' => ['photos' => []],
        ]),
        'https://api.telegram.org/bot123456:fake-token/setMyCommands' => Http::response([
            'ok' => true,
            'result' => true,
        ]),
    ]);

    $this->actingAs($user)->post(route('app.omnichat.telegram.store'), [
        'token' => '123456:fake-token',
    ])->assertRedirect();

    $channel = OmnichatChannel::query()->where('workspace_id', $workspace->id)->sole();
    expect($channel->provider)->toBe(ChannelProvider::Telegram)
        ->and($channel->external_id)->toBe('king_coffee_bot')
        ->and($channel->status)->toBe(ChannelStatus::Connected)
        ->and($channel->name)->toContain('King Coffee Bot');
});

it('receives a telegram inbound webhook and dispatches queue processing', function (): void {
    Queue::fake();

    $channel = OmnichatChannel::query()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'test_bot',
        'name' => 'Test Bot',
        'access_token' => 'fake-token',
        'webhook_secret' => 'my-secure-secret-token',
        'status' => ChannelStatus::Connected,
    ]);

    $payload = [
        'update_id' => 10001,
        'message' => [
            'message_id' => 456,
            'from' => [
                'id' => 777888999,
                'is_bot' => false,
                'first_name' => 'Nguyễn',
                'last_name' => 'Văn A',
                'username' => 'nguyenvana',
            ],
            'chat' => [
                'id' => 777888999,
                'type' => 'private',
            ],
            'date' => time(),
            'text' => 'Xin chào shop, shop có áo sơ mi trắng không? SĐT 0912345678',
        ],
    ];

    // Wrong secret rejected with 403
    $this->postJson(route('omnichat.telegram.webhook', $channel), $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ])->assertForbidden();

    // Correct secret accepted with 204
    $this->postJson(route('omnichat.telegram.webhook', $channel), $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => 'my-secure-secret-token',
    ])->assertNoContent();

    $event = OmnichatWebhookEvent::query()->where('provider', 'telegram')->sole();
    expect($event->workspace_id)->toBe($channel->workspace_id)
        ->and($event->external_event_id)->toBe("{$channel->id}:10001");

    Queue::assertPushed(ProcessTelegramOmnichatWebhook::class);
});

it('processes telegram webhook to create customer profile, identity, conversation and message', function (): void {
    Event::fake();

    $workspace = Workspace::factory()->create();
    $channel = OmnichatChannel::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'shop_bot',
        'name' => 'Shop Bot',
        'access_token' => 'test-token',
        'webhook_secret' => 'secret',
        'status' => ChannelStatus::Connected,
    ]);

    $event = OmnichatWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'external_event_id' => "{$channel->id}:9999",
        'event_type' => 'message',
        'payload' => [
            'channel_id' => $channel->id,
            'message' => [
                'message_id' => 888,
                'from' => [
                    'id' => 12345678,
                    'is_bot' => false,
                    'first_name' => 'Trần',
                    'last_name' => 'Thị B',
                    'username' => 'tranthib',
                ],
                'chat' => [
                    'id' => 12345678,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Tư vấn cho mình sản phẩm này nhé! SĐT: 0987654321',
            ],
        ],
        'status' => 'pending',
        'received_at' => now(),
    ]);

    Http::fake([
        'https://api.telegram.org/bottest-token/getUserProfilePhotos*' => Http::response([
            'ok' => true,
            'result' => ['photos' => []],
        ]),
    ]);

    (new ProcessTelegramOmnichatWebhook($event))->handle();

    // 1. Verify separate Customer Profile
    $contact = OmnichatContact::query()->where('workspace_id', $workspace->id)->sole();
    expect($contact->display_name)->toBe('Trần Thị B')
        ->and($contact->phone)->toBe('0987654321');

    // 2. Verify Contact Identity for Telegram
    $identity = OmnichatContactIdentity::query()->where('contact_id', $contact->id)->sole();
    expect($identity->provider)->toBe('telegram')
        ->and($identity->external_id)->toBe('12345678');

    // 3. Verify Conversation
    $conversation = OmnichatConversation::query()->where('channel_id', $channel->id)->sole();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->external_id)->toBe('12345678');

    // 4. Verify Inbound Message
    $message = OmnichatMessage::query()->where('conversation_id', $conversation->id)->sole();
    expect($message->direction)->toBe('inbound')
        ->and($message->body)->toBe('Tư vấn cho mình sản phẩm này nhé! SĐT: 0987654321')
        ->and($message->sender_contact_id)->toBe($contact->id);
});

it('sends an outbound message to telegram via StoreMessage', function (): void {
    [$user, $workspace] = telegramAdminUser();

    $channel = OmnichatChannel::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'shop_bot',
        'name' => 'Shop Bot',
        'access_token' => 'outbound-token',
        'webhook_secret' => 'secret',
        'status' => ChannelStatus::Connected,
    ]);

    $contact = OmnichatContact::query()->create([
        'workspace_id' => $workspace->id,
        'display_name' => 'Lê Văn C',
        'status' => 'active',
    ]);

    $conversation = OmnichatConversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'contact_id' => $contact->id,
        'external_id' => '555666777',
        'status' => 'open',
    ]);

    Http::fake([
        'https://api.telegram.org/botoutbound-token/sendMessage' => Http::response([
            'ok' => true,
            'result' => [
                'message_id' => 999111,
            ],
        ]),
    ]);

    $storeMessage = app(StoreMessage::class);
    $msg = $storeMessage->execute(
        conversation: $conversation,
        sender: $user,
        body: 'Dạ chào bạn, shop có sẵn hàng ạ!',
        mode: 'reply',
        clientId: (string) Str::uuid(),
    );

    expect($msg->direction)->toBe('outbound')
        ->and($msg->body)->toBe('Dạ chào bạn, shop có sẵn hàng ạ!')
        ->and($msg->external_id)->toBe('999111')
        ->and($msg->sender_user_id)->toBe($user->id);
});

it('can toggle AI handover state on a conversation', function (): void {
    [$user, $workspace] = telegramAdminUser();

    $contact = OmnichatContact::query()->create([
        'workspace_id' => $workspace->id,
        'display_name' => 'Test User',
        'status' => 'active',
    ]);

    $conversation = OmnichatConversation::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'external_id' => 'conv_123',
        'status' => 'open',
        'meta' => ['ai_paused' => false],
    ]);

    $this->actingAs($user)
        ->post(route('app.omnichat.conversations.ai-toggle', $conversation))
        ->assertRedirect();

    expect($conversation->fresh()->meta['ai_paused'])->toBeTrue();

    // Toggle back
    $this->actingAs($user)
        ->post(route('app.omnichat.conversations.ai-toggle', $conversation))
        ->assertRedirect();

    expect($conversation->fresh()->meta['ai_paused'])->toBeFalse();
});

it('can re-sync webhook for telegram channel', function (): void {
    [$user, $workspace] = telegramAdminUser();

    $channel = OmnichatChannel::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'sync_bot',
        'name' => 'Sync Bot',
        'access_token' => 'sync-token-123',
        'webhook_secret' => 'sync-secret',
        'status' => ChannelStatus::Connected,
    ]);

    Http::fake([
        'https://api.telegram.org/botsync-token-123/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was set',
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('app.omnichat.telegram.sync-webhook', $channel))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('can fetch webhook info for telegram channel', function (): void {
    [$user, $workspace] = telegramAdminUser();

    $channel = OmnichatChannel::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'info_bot',
        'name' => 'Info Bot',
        'access_token' => 'info-token-123',
        'webhook_secret' => 'info-secret',
        'status' => ChannelStatus::Connected,
    ]);

    Http::fake([
        'https://api.telegram.org/botinfo-token-123/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://example.com/webhooks/telegram/'.$channel->id,
                'has_custom_certificate' => false,
                'pending_update_count' => 3,
            ],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('app.omnichat.telegram.webhook-info', $channel));

    $response->assertSuccessful()
        ->assertJsonPath('info.result.url', 'https://example.com/webhooks/telegram/'.$channel->id)
        ->assertJsonPath('info.result.pending_update_count', 3);
});

it('can execute omnichat:telegram-webhook command to sync and inspect', function (): void {
    [$user, $workspace] = telegramAdminUser();

    $channel = OmnichatChannel::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => ChannelProvider::Telegram,
        'external_id' => 'cli_bot',
        'name' => 'CLI Bot',
        'access_token' => 'cli-token-123',
        'webhook_secret' => 'cli-secret',
        'status' => ChannelStatus::Connected,
    ]);

    Http::fake([
        'https://api.telegram.org/botcli-token-123/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        'https://api.telegram.org/botcli-token-123/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://example.com/webhooks/telegram/'.$channel->id,
                'pending_update_count' => 0,
            ],
        ]),
    ]);

    $this->artisan('omnichat:telegram-webhook', [
        'channel_id' => $channel->id,
        '--sync' => true,
    ])
        ->expectsOutputToContain('Kênh: CLI Bot')
        ->expectsOutputToContain('Đã đăng ký Webhook thành công')
        ->assertExitCode(0);
});
