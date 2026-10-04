<?php

declare(strict_types=1);

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Events\OmnichatMessageCreated;
use App\Listeners\Omnichat\HandlePageAiCareAutoReply;
use App\Models\AiBot;
use App\Models\OmnichatChannel;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Dify\DifyChatClient;
use App\Support\Omnichat\FacebookMessengerClient;
use App\Support\Omnichat\RemoteImageDownloader;
use App\Support\Omnichat\TelegramOmnichatClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('user can view ai settings page with connected pages and schedule', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'display_name' => 'Fashion Shop Page',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get('/settings/account/ai');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/account/Ai')
        ->has('pages')
    );
});

test('user can update ai care and operating schedule for a specific page', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'display_name' => 'Fashion Shop Page',
        'is_active' => true,
    ]);

    $payload = [
        'enabled' => true,
        'bot_name' => 'Bot Fashion VIP',
        'persona' => 'Bạn là trợ lý AI chuyên nghiệp tư vấn thời trang nữ.',
        'model' => 'gpt-4o-mini',
        'reply_mode' => 'delayed',
        'reply_delay_seconds' => 3,
        'operating_hours' => [
            'mode' => 'custom',
            'days' => [1, 2, 3, 4, 5, 6],
            'start_time' => '08:30',
            'end_time' => '21:30',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ],
        'off_hours_behavior' => 'ai_reply',
        'off_hours_message' => 'Shop đang ngoài giờ làm việc, AI xin phép trả lời trước ạ!',
        'auto_tag_leads' => true,
        'lead_keywords' => ['mua ngay', 'báo giá', 'size L'],
        'knowledge_base' => 'Chính sách đổi trả 7 ngày.',
    ];

    $response = $this->actingAs($this->user)
        ->put("/settings/account/ai/pages/{$account->id}", $payload);

    $response->assertRedirect();
    $account->refresh();

    expect($account->meta['ai_care']['enabled'])->toBeTrue();
    expect($account->meta['ai_care']['bot_name'])->toBe('Bot Fashion VIP');
    expect($account->meta['ai_care']['operating_hours']['mode'])->toBe('custom');
    expect($account->meta['ai_care']['operating_hours']['start_time'])->toBe('08:30');
});

test('user can batch update ai care settings to all pages', function () {
    $account1 = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'is_active' => true,
    ]);

    $account2 = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'is_active' => true,
    ]);

    $payload = [
        'enabled' => true,
        'persona' => 'Hỗ trợ khách hàng chung của toàn hệ thống.',
        'reply_delay_seconds' => 5,
        'operating_hours' => [
            'mode' => '24/7',
            'days' => [1, 2, 3, 4, 5, 6, 7],
            'start_time' => '08:00',
            'end_time' => '22:00',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ],
    ];

    $response = $this->actingAs($this->user)
        ->put('/settings/account/ai/pages', $payload);

    $response->assertRedirect();

    $account1->refresh();
    $account2->refresh();

    expect($account1->meta['ai_care']['enabled'])->toBeTrue();
    expect($account2->meta['ai_care']['enabled'])->toBeTrue();
});

test('inbound omnichat message triggers ai auto reply via dify', function () {
    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Trading Coach',
        'response_language' => 'en',
    ]);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'is_active' => true,
        'meta' => [
            'ai_care' => [
                'enabled' => true,
                'provider' => 'dify',
                'dify_api_key' => 'app-test-key-123',
                'dify_base_url' => 'https://kingai.tnicorporation.com/v1',
                'bot_id' => $bot->id,
                'operating_hours' => ['mode' => '24/7'],
                'reply_delay_seconds' => 0,
            ],
        ],
    ]);

    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'external_id' => 'fb-user-123',
    ]);

    $difyClientMock = mock(DifyChatClient::class);
    $difyClientMock->shouldReceive('sendMessage')
        ->withArgs(function (string $query, ?string $conversationId, string $user, array $inputs): bool {
            expect($inputs)->toMatchArray([
                'bot_name' => 'Trading Coach',
                'response_language' => 'en',
            ]);

            return true;
        })
        ->once()
        ->andReturn([
            'answer' => 'Chào bạn! King Coffee rất hân hạnh được phục vụ bạn.',
            'conversation_id' => 'dify-conv-123',
        ]);
    app()->instance(DifyChatClient::class, $difyClientMock);

    $fbClientMock = mock(FacebookMessengerClient::class);
    $fbClientMock->shouldReceive('sendText')
        ->withAnyArgs()
        ->once()
        ->andReturn(['id' => 'mid-reply-123', 'payload' => []]);
    app()->instance(FacebookMessengerClient::class, $fbClientMock);

    $inboundMessage = OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'body' => 'Tư vấn giúp tôi loại cà phê đậm vị nhé',
    ]);

    $listener = app(HandlePageAiCareAutoReply::class);
    $listener->handle(new OmnichatMessageCreated($inboundMessage));

    $outboundMessage = OmnichatMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->latest('id')
        ->first();

    expect($outboundMessage)->not->toBeNull();
    expect($outboundMessage->body)->toBe('Chào bạn! King Coffee rất hân hạnh được phục vụ bạn.');
});

test('telegram ai reply downloads and sends linked images as photo messages', function (): void {
    $channel = OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => ChannelProvider::Telegram,
        'settings' => [
            'ai_care' => [
                'enabled' => true,
                'provider' => 'dify',
                'dify_api_key' => 'telegram-app-key',
                'dify_base_url' => 'https://kingai.tnicorporation.com/v1',
                'operating_hours' => ['mode' => '24/7'],
            ],
        ],
    ]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'channel_id' => $channel->id,
        'social_account_id' => null,
        'external_id' => 'telegram-chat-123',
    ]);

    $difyClientMock = mock(DifyChatClient::class);
    $difyClientMock->shouldReceive('sendMessage')
        ->once()
        ->andReturn([
            'answer' => "Đây là sản phẩm bạn hỏi.\n\n![Ảnh sản phẩm](https://8.8.8.8/product.jpg)",
            'conversation_id' => 'dify-telegram-conv-123',
        ]);
    app()->instance(DifyChatClient::class, $difyClientMock);

    $telegramClientMock = mock(TelegramOmnichatClient::class);
    $telegramClientMock->shouldReceive('sendMessage')
        ->once()
        ->andReturn(['id' => 'telegram-text-1', 'payload' => []]);
    $telegramClientMock->shouldReceive('sendPhoto')
        ->once()
        ->withArgs(fn (OmnichatChannel $sentChannel, string $chatId, UploadedFile $image): bool => $sentChannel->is($channel)
            && $chatId === 'telegram-chat-123'
            && $image->getClientOriginalName() === 'product.jpg')
        ->andReturn([
            'id' => 'telegram-photo-1',
            'payload' => ['ok' => true],
            'attachment' => [
                'id' => 'telegram-file-1',
                'type' => 'image',
                'url' => '',
                'original_name' => 'product.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 12,
            ],
        ]);
    app()->instance(TelegramOmnichatClient::class, $telegramClientMock);

    $imageContent = UploadedFile::fake()->image('fixture.jpg')->getContent();
    Http::fake([
        'https://8.8.8.8/product.jpg' => Http::response($imageContent, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $inboundMessage = OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'channel_id' => $channel->id,
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'body' => 'Gửi hình cho tôi xem',
    ]);

    app(HandlePageAiCareAutoReply::class)->handle(new OmnichatMessageCreated($inboundMessage));

    $outboundMessages = OmnichatMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->get();

    expect($outboundMessages)->toHaveCount(2)
        ->and($outboundMessages->firstWhere('type', 'text')?->body)->toBe('Đây là sản phẩm bạn hỏi.')
        ->and($outboundMessages->firstWhere('type', 'image')?->status)->toBe('sent');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://8.8.8.8/product.jpg');
});

test('telegram ai care sends only one image batch during a burst in the same conversation', function (): void {
    $channel = OmnichatChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => ChannelProvider::Telegram,
        'settings' => [
            'ai_care' => [
                'enabled' => true,
                'provider' => 'dify',
                'dify_api_key' => 'telegram-app-key',
                'dify_base_url' => 'https://kingai.tnicorporation.com/v1',
                'operating_hours' => ['mode' => '24/7'],
            ],
        ],
    ]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'channel_id' => $channel->id,
        'social_account_id' => null,
        'external_id' => 'telegram-burst-chat',
    ]);

    $difyClientMock = mock(DifyChatClient::class);
    $difyClientMock->shouldReceive('sendMessage')
        ->twice()
        ->andReturn(
            [
                'answer' => "Thông tin dịch vụ.\n\n![Kết quả](https://8.8.8.8/result.jpg)",
                'conversation_id' => 'dify-burst-conv',
            ],
            [
                'answer' => "Thông tin thêm.\n\n![Kết quả](https://8.8.8.8/result.jpg)",
                'conversation_id' => 'dify-burst-conv',
            ],
        );
    app()->instance(DifyChatClient::class, $difyClientMock);

    $telegramClientMock = mock(TelegramOmnichatClient::class);
    $telegramClientMock->shouldReceive('sendMessage')->twice()
        ->andReturn(
            ['id' => 'telegram-text-1', 'payload' => []],
            ['id' => 'telegram-text-2', 'payload' => []],
        );
    $telegramClientMock->shouldReceive('sendPhoto')->once()
        ->andReturn([
            'id' => 'telegram-photo',
            'payload' => ['ok' => true],
            'attachment' => [
                'id' => 'telegram-file',
                'type' => 'image',
                'url' => '',
                'original_name' => 'result.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 12,
            ],
        ]);
    app()->instance(TelegramOmnichatClient::class, $telegramClientMock);

    $imageContent = UploadedFile::fake()->image('fixture.jpg')->getContent();
    Http::fake([
        'https://8.8.8.8/result.jpg' => Http::response($imageContent, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    foreach (['Giá dịch vụ thế nào?', 'Cho mình biết thêm'] as $body) {
        $inboundMessage = OmnichatMessage::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => $body,
        ]);

        app(HandlePageAiCareAutoReply::class)->handle(new OmnichatMessageCreated($inboundMessage));
    }

    expect(OmnichatMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->where('type', 'image')
        ->count())->toBe(1);

    expect(OmnichatMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->where('type', 'text')
        ->count())->toBe(2);
});

test('remote ai images cannot be downloaded from private network addresses', function (): void {
    expect(fn () => app(RemoteImageDownloader::class)->withDownloadedImage(
        'https://127.0.0.1/private.jpg',
        fn (UploadedFile $image): null => null,
    ))->toThrow(RuntimeException::class);
});
