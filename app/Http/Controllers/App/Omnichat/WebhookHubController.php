<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessFacebookMessengerWebhook;
use App\Jobs\Omnichat\ProcessLazadaWebhook;
use App\Jobs\Omnichat\ProcessTelegramOmnichatWebhook;
use App\Jobs\Omnichat\ProcessZaloOaWebhook;
use App\Models\OmnichatChannel;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookHubController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $baseUrl = rtrim((string) (config('app.webhook_url') ?: config('app.url')), '/');

        // Endpoints configuration summary for all platforms
        $endpoints = [
            [
                'provider' => 'telegram',
                'name' => 'Telegram Bot',
                'icon' => 'telegram',
                'endpoint' => "{$baseUrl}/webhooks/telegram/{channel_id}",
                'method' => 'POST',
                'secret_header' => 'X-Telegram-Bot-Api-Secret-Token',
                'description' => 'Nhận tin nhắn, hình ảnh, văn bản và tương tác bot từ Telegram API.',
                'guide' => 'Hệ thống tự động đăng ký webhook ngay khi dán Token BotFather. Nếu chạy local, hãy cập nhật WEBHOOK_URL.',
                'connected_count' => OmnichatChannel::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('provider', 'telegram')
                    ->where('status', 'connected')
                    ->count(),
            ],
            [
                'provider' => 'facebook',
                'name' => 'Facebook Messenger & Fanpage',
                'icon' => 'facebook',
                'endpoint' => "{$baseUrl}/facebook/messenger/webhook",
                'method' => 'POST / GET',
                'verify_token' => (string) config('services.facebook.webhook_verify_token', 'kinghub_verify_token'),
                'description' => 'Nhận tin nhắn Messenger, bình luận Fanpage và Instagram Direct Messages.',
                'guide' => 'Điền Callback URL và Verify Token vào Cài đặt Webhooks trong Meta for Developers.',
                'connected_count' => SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('platform', ['facebook', 'instagram'])
                    ->where('is_active', true)
                    ->count(),
            ],
            [
                'provider' => 'zalo',
                'name' => 'Zalo Official Account (Zalo OA)',
                'icon' => 'zalo',
                'endpoint' => "{$baseUrl}/webhooks/zalo-oa",
                'method' => 'POST',
                'secret_header' => 'X-Zalo-OA-Secret / OA ID',
                'description' => 'Nhận tin nhắn quan tâm, văn bản, ảnh từ người dùng Zalo gửi tới Official Account.',
                'guide' => 'Điền Webhook URL vào trang Cấu hình Webhook của ứng dụng Zalo for Developers.',
                'connected_count' => SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('platform', 'zalo')
                    ->where('is_active', true)
                    ->count(),
            ],
            [
                'provider' => 'shopee',
                'name' => 'Shopee Open Platform',
                'icon' => 'shopee',
                'endpoint' => "{$baseUrl}/webhooks/shopee",
                'method' => 'POST',
                'secret_header' => 'Shopee Signature',
                'description' => 'Nhận tin nhắn chat từ khách hàng trên Shopee Shop về OmniChat.',
                'guide' => 'Cài đặt Push Notification URL trong Shopee Open Platform Console.',
                'connected_count' => OmnichatChannel::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('provider', 'shopee')
                    ->where('status', 'connected')
                    ->count(),
            ],
            [
                'provider' => 'lazada',
                'name' => 'Lazada Open Platform',
                'icon' => 'lazada',
                'endpoint' => "{$baseUrl}/webhooks/lazada",
                'method' => 'POST',
                'secret_header' => 'Lazada Sign',
                'description' => 'Nhận tin nhắn khách hàng từ Lazada Chat về OmniChat.',
                'guide' => 'Cấu hình Webhook Callback URL trong Lazada Open Platform.',
                'connected_count' => OmnichatChannel::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('provider', 'lazada')
                    ->where('status', 'connected')
                    ->count(),
            ],
        ];

        // Fetch recent webhook events for this workspace
        $events = OmnichatWebhookEvent::query()
            ->where(function ($query) use ($workspace): void {
                $query->where('workspace_id', $workspace->id)
                    ->orWhereNull('workspace_id');
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OmnichatWebhookEvent $e): array => [
                'id' => $e->id,
                'provider' => $e->provider,
                'event_type' => $e->event_type ?? 'message',
                'external_event_id' => $e->external_event_id,
                'status' => $e->status,
                'attempts' => $e->attempts,
                'received_at' => $e->received_at?->toIso8601String() ?? $e->created_at->toIso8601String(),
                'processed_at' => $e->processed_at?->toIso8601String(),
                'error_message' => $e->error_message,
                'payload_preview' => is_array($e->payload) ? array_slice($e->payload, 0, 5) : [],
            ]);

        $counts = [
            'total' => OmnichatWebhookEvent::query()->where('workspace_id', $workspace->id)->count(),
            'processed' => OmnichatWebhookEvent::query()->where('workspace_id', $workspace->id)->where('status', 'processed')->count(),
            'failed' => OmnichatWebhookEvent::query()->where('workspace_id', $workspace->id)->where('status', 'failed')->count(),
            'pending' => OmnichatWebhookEvent::query()->where('workspace_id', $workspace->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('omnichat/WebhooksHub', [
            'endpoints' => $endpoints,
            'events' => $events,
            'counts' => $counts,
            'baseUrl' => $baseUrl,
        ]);
    }

    public function retry(Request $request, OmnichatWebhookEvent $event): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('manageAccounts', $workspace);

        if ($event->workspace_id && $event->workspace_id !== $workspace->id) {
            abort(404);
        }

        $event->update([
            'status' => 'pending',
            'attempts' => $event->attempts + 1,
            'error_message' => null,
        ]);

        // Dispatch appropriate job
        match (strtolower((string) $event->provider)) {
            'telegram' => ProcessTelegramOmnichatWebhook::dispatch($event),
            'facebook', 'messenger', 'instagram' => ProcessFacebookMessengerWebhook::dispatch($event),
            'zalo', 'zalo_oa' => ProcessZaloOaWebhook::dispatch($event),
            'lazada' => ProcessLazadaWebhook::dispatch($event),
            default => null,
        };

        return back()->with('success', "Đã đẩy sự kiện #{$event->id} vào hàng đợi để xử lý lại.");
    }
}
