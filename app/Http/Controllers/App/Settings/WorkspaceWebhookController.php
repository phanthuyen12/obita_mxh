<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Settings\StoreWorkspaceWebhookRequest;
use App\Http\Requests\App\Settings\UpdateWorkspaceWebhookRequest;
use App\Jobs\SendWorkspaceWebhookJob;
use App\Models\WorkspaceWebhook;
use App\Models\WorkspaceWebhookDelivery;
use App\Support\Webhook\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkspaceWebhookController extends Controller
{
    public const AVAILABLE_EVENTS = [
        [
            'id' => 'message.inbound',
            'label' => 'Tin nhắn đến (Inbound)',
            'group' => 'OmniChat',
            'description' => 'Khách hàng nhắn tin từ Telegram, Facebook, Zalo, Shopee...',
        ],
        [
            'id' => 'message.outbound',
            'label' => 'Tin nhắn đi (Outbound)',
            'group' => 'OmniChat',
            'description' => 'Nhân viên hoặc AI trả lời tin nhắn của khách hàng',
        ],
        [
            'id' => 'contact.created',
            'label' => 'Khách hàng mới (Contact)',
            'group' => 'CRM & Leads',
            'description' => 'Khách hàng mới được khởi tạo Profile CRM',
        ],
        [
            'id' => 'lead.detected',
            'label' => 'Phát hiện Lead (Số điện thoại/Nhu cầu)',
            'group' => 'CRM & Leads',
            'description' => 'Hệ thống phát hiện khách để lại số điện thoại hoặc thông tin mua hàng',
        ],
        [
            'id' => 'conversation.handover',
            'label' => 'Chuyển giao AI Handover (/human)',
            'group' => 'AI & CSKH',
            'description' => 'Khách hàng yêu cầu gặp tư vấn viên hoặc AI tự động tạm dừng',
        ],
        [
            'id' => 'post.published',
            'label' => 'Bài đăng xuất bản thành công',
            'group' => 'Mạng xã hội',
            'description' => 'Bài đăng trên Fanpage, Instagram, TikTok... xuất bản thành công',
        ],
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $webhooks = WorkspaceWebhook::query()
            ->where('workspace_id', $workspace->id)
            ->withCount([
                'deliveries as total_deliveries',
                'deliveries as failed_deliveries' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->latest()
            ->get()
            ->map(fn (WorkspaceWebhook $wh) => [
                'id' => $wh->id,
                'name' => $wh->name,
                'url' => $wh->url,
                'secret' => $wh->secret,
                'events' => $wh->events ?? [],
                'is_active' => (bool) $wh->is_active,
                'total_deliveries' => $wh->total_deliveries,
                'failed_deliveries' => $wh->failed_deliveries,
                'created_at' => $wh->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/workspace/Webhooks', [
            'workspace' => $workspace,
            'webhooks' => $webhooks,
            'availableEvents' => self::AVAILABLE_EVENTS,
        ]);
    }

    public function store(StoreWorkspaceWebhookRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $validated = $request->validated();

        WorkspaceWebhook::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => Str::random(64),
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Đã thêm Webhook mới thành công.');
    }

    public function update(
        UpdateWorkspaceWebhookRequest $request,
        WorkspaceWebhook $webhook,
    ): RedirectResponse {
        $this->authorizeWebhook($request, $webhook);

        $webhook->update($request->validated());

        return back()->with('success', 'Đã cập nhật cấu hình Webhook.');
    }

    public function destroy(Request $request, WorkspaceWebhook $webhook): RedirectResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $webhook->delete();

        return back()->with('success', 'Đã xóa Webhook.');
    }

    public function test(Request $request, WorkspaceWebhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $testEvent = 'test.ping';
        $testPayload = [
            'event' => $testEvent,
            'workspace_id' => $webhook->workspace_id,
            'timestamp' => time(),
            'message' => 'Xin chào! Đây là request kiểm tra kết nối Webhook từ King Hub.',
            'sample_data' => [
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
            ],
        ];

        $jsonPayload = json_encode($testPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $timestamp = time();
        $signature = WebhookDispatcher::signPayload($jsonPayload, $webhook->secret, $timestamp);

        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'KingHub-Webhook/1.0',
                    'X-KingHub-Event' => $testEvent,
                    'X-KingHub-Delivery' => (string) Str::uuid(),
                    'X-KingHub-Timestamp' => (string) $timestamp,
                    'X-KingHub-Signature' => $signature,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Record test delivery
            $delivery = WorkspaceWebhookDelivery::query()->create([
                'workspace_webhook_id' => $webhook->id,
                'event' => $testEvent,
                'payload' => $testPayload,
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 2000),
                'duration_ms' => $durationMs,
                'status' => $response->successful() ? 'success' : 'failed',
                'error_message' => $response->successful() ? null : 'HTTP '.$response->status(),
            ]);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'duration_ms' => $durationMs,
                'response_body' => Str::limit($response->body(), 500),
                'delivery_id' => $delivery->id,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $delivery = WorkspaceWebhookDelivery::query()->create([
                'workspace_webhook_id' => $webhook->id,
                'event' => $testEvent,
                'payload' => $testPayload,
                'duration_ms' => $durationMs,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 0,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id,
            ], 422);
        }
    }

    public function deliveries(Request $request, WorkspaceWebhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $deliveries = $webhook->deliveries()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (WorkspaceWebhookDelivery $del) => [
                'id' => $del->id,
                'event' => $del->event,
                'payload' => $del->payload,
                'response_status' => $del->response_status,
                'response_body' => $del->response_body,
                'duration_ms' => $del->duration_ms,
                'status' => $del->status,
                'attempts' => $del->attempts,
                'error_message' => $del->error_message,
                'created_at' => $del->created_at->toIso8601String(),
            ]);

        return response()->json([
            'deliveries' => $deliveries,
        ]);
    }

    public function resend(Request $request, WorkspaceWebhookDelivery $delivery): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $delivery->webhook && $delivery->webhook->workspace_id === $workspace?->id,
            404,
        );
        $this->authorize('manageTeam', $workspace);

        $delivery->update([
            'status' => 'pending',
            'attempts' => $delivery->attempts + 1,
        ]);

        SendWorkspaceWebhookJob::dispatch($delivery);

        return response()->json([
            'success' => true,
            'message' => 'Đã đưa yêu cầu gửi lại vào hàng đợi.',
        ]);
    }

    private function authorizeWebhook(Request $request, WorkspaceWebhook $webhook): void
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && $webhook->workspace_id === $workspace->id,
            404,
        );
        $this->authorize('manageTeam', $workspace);
    }
}
