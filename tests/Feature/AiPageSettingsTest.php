<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Events\OmnichatMessageCreated;
use App\Listeners\Omnichat\HandlePageAiCareAutoReply;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Dify\DifyChatClient;
use App\Support\Omnichat\FacebookMessengerClient;

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
        ->withAnyArgs()
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
