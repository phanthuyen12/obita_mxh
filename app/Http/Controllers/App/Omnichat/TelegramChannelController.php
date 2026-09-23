<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\StoreTelegramChannelRequest;
use App\Http\Requests\App\Omnichat\UpdateTelegramChannelRequest;
use App\Models\OmnichatChannel;
use App\Support\Omnichat\TelegramOmnichatClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelegramChannelController extends Controller
{
    public function index(Request $request, TelegramOmnichatClient $client): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('manageAccounts', $workspace);

        $channels = OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', ChannelProvider::Telegram)
            ->latest()
            ->get()
            ->map(fn (OmnichatChannel $channel): array => [
                'id' => $channel->id,
                'name' => $channel->name,
                'username' => data_get($channel->settings, 'bot_username'),
                'mode' => data_get($channel->settings, 'mode', 'bot'),
                'business' => data_get($channel->settings, 'business'),
                'avatar_url' => $channel->avatar_url,
                'status' => $channel->status->value,
                'webhook_url' => $client->buildWebhookUrl($channel),
                'settings' => $channel->settings ?? [],
                'created_at' => $channel->created_at->toIso8601String(),
            ]);

        return Inertia::render('omnichat/TelegramChannels', [
            'channels' => $channels,
        ]);
    }

    public function store(StoreTelegramChannelRequest $request, TelegramOmnichatClient $client): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $token = trim($request->validated('token'));
        $mode = $request->validated('mode', 'bot');

        try {
            $botInfo = $client->verifyToken($token);
        } catch (Throwable $e) {
            return back()->withErrors(['token' => 'Bot Token không hợp lệ hoặc không thể kết nối tới Telegram: '.$e->getMessage()]);
        }

        $botId = (int) data_get($botInfo, 'id');
        $firstName = (string) data_get($botInfo, 'first_name', 'Telegram Bot');
        $username = data_get($botInfo, 'username');
        $botName = $username ? "{$firstName} (@{$username})" : $firstName;
        $externalId = $username ? (string) $username : (string) $botId;
        $webhookSecret = Str::random(64);

        if ($mode === 'business' && data_get($botInfo, 'can_connect_to_business') !== true) {
            return back()->withErrors(['token' => 'Bot này chưa được bật Secretary Mode trong @BotFather, không thể kết nối với Telegram cá nhân.']);
        }

        $settings = [
            'mode' => $mode,
            'bot_id' => $botId,
            'bot_username' => $username,
            'ai_care' => [
                'enabled' => false,
                'provider' => 'dify',
            ],
        ];

        if ($mode === 'business') {
            $settings['business'] = null;
        }

        /** @var OmnichatChannel $channel */
        $channel = OmnichatChannel::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => ChannelProvider::Telegram,
                'external_id' => $externalId,
            ],
            [
                'name' => $botName,
                'access_token' => $token,
                'webhook_secret' => $webhookSecret,
                'status' => ChannelStatus::Connected,
                'settings' => $settings,
                'connected_at' => now(),
                'disconnected_at' => null,
            ],
        );

        // Register Webhook
        $webhookUrl = $client->buildWebhookUrl($channel);
        $client->setWebhook($channel, $webhookUrl);

        if ($mode === 'business') {
            return back()->with(
                'success',
                "Đã tạo kênh Telegram cá nhân. Bây giờ hãy mở Telegram trên điện thoại → Settings → Telegram Business → Chatbots và thêm bot @{$username} để hoàn tất kết nối."
            );
        }

        // Fetch avatar if available
        $avatar = $client->fetchUserProfilePhoto($channel, $botId);
        if ($avatar !== null) {
            $channel->update(['avatar_url' => $avatar]);
        }

        // Set default bot commands
        $client->setMyCommands($channel, [
            ['command' => 'start', 'description' => 'Bắt đầu trò chuyện'],
            ['command' => 'human', 'description' => 'Gặp nhân viên tư vấn'],
        ]);

        return back()->with('success', "Đã kết nối thành công Telegram Bot @{$username}!");
    }

    public function update(
        UpdateTelegramChannelRequest $request,
        OmnichatChannel $channel,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);

        $validated = $request->validated();
        $settings = $channel->settings ?? [];

        if (isset($validated['ai_care'])) {
            // Replace entire ai_care block — do NOT merge so stale values (e.g. enabled=false) cannot persist
            $existing = $settings['ai_care'] ?? [];
            $settings['ai_care'] = array_merge($existing, $validated['ai_care']);

            // Ensure enabled is always cast to a real boolean
            $settings['ai_care']['enabled'] = (bool) ($settings['ai_care']['enabled'] ?? false);
        }

        $updateData = ['settings' => $settings];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        $channel->update($updateData);

        return back()->with('success', 'Đã cập nhật cấu hình Telegram Bot thành công.');
    }

    public function syncWebhook(
        Request $request,
        OmnichatChannel $channel,
        TelegramOmnichatClient $client,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);

        $webhookUrl = $client->buildWebhookUrl($channel);
        $success = $client->setWebhook($channel, $webhookUrl);

        if (! $success) {
            return back()->withErrors(['webhook' => 'Không thể đồng bộ Webhook với Telegram API. Vui lòng kiểm tra lại token hoặc URL.']);
        }

        return back()->with('success', "Đã đồng bộ Webhook thành công với URL: {$webhookUrl}");
    }

    public function webhookInfo(
        Request $request,
        OmnichatChannel $channel,
        TelegramOmnichatClient $client,
    ): JsonResponse {
        $this->authorizeChannel($request, $channel);

        $info = $client->getWebhookInfo($channel);

        return response()->json([
            'webhook_url' => $client->buildWebhookUrl($channel),
            'info' => $info,
        ]);
    }

    public function destroy(
        Request $request,
        OmnichatChannel $channel,
        TelegramOmnichatClient $client,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);

        try {
            $client->deleteWebhook($channel);
        } catch (Throwable) {
            // Ignore if webhook deletion fails on telegram side
        }

        $channel->update([
            'status' => ChannelStatus::Disconnected,
            'disconnected_at' => now(),
        ]);

        return back()->with('success', 'Đã ngắt kết nối Telegram Bot.');
    }

    private function authorizeChannel(Request $request, OmnichatChannel $channel): void
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $channel->workspace_id === $workspace->id && $channel->provider === ChannelProvider::Telegram,
            404,
        );
        $this->authorize('manageAccounts', $workspace);
    }
}
