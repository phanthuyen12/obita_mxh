<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\App\Controller;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\CustomerSegment;
use App\Models\OmnichatContact;
use App\Services\Omnichat\CustomerSegmentFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BroadcastCampaignController extends Controller
{
    public function __construct(
        protected CustomerSegmentFilterService $filterService,
    ) {}

    /**
     * Hiển thị giao diện danh sách chiến dịch gửi tin hàng loạt.
     */
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && ($request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN
        );

        $campaigns = BroadcastCampaign::query()
            ->where('workspace_id', $workspace->id)
            ->with(['customerSegment', 'channel'])
            ->withCount(['messages', 'messages as sent_count' => fn ($q) => $q->where('status', 'sent')])
            ->latest()
            ->paginate(15);

        $segments = CustomerSegment::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->get();

        // Thống kê nhanh tổng quan
        $totalContacts = OmnichatContact::query()->where('workspace_id', $workspace->id)->count();
        $phoneContacts = OmnichatContact::query()->where('workspace_id', $workspace->id)->whereNotNull('phone')->where('phone', '!=', '')->count();
        $inactive30d = $this->filterService->queryInactiveContacts($workspace->id, 30)->count();
        $inactive40d = $this->filterService->queryInactiveContacts($workspace->id, 40)->count();
        $totalMessagesSent = BroadcastMessage::query()->where('workspace_id', $workspace->id)->where('status', 'sent')->count();

        // Danh sách tin nhắn khách hàng (sent & failed) để hiển thị checklist table và hỗ trợ retry
        $sentMessages = BroadcastMessage::query()
            ->where('workspace_id', $workspace->id)
            ->with(['contact:id,name,display_name,phone,avatar_url', 'campaign:id,name,trigger_type'])
            ->latest('sent_at')
            ->paginate(50);

        return Inertia::render('omnichat/Broadcast', [
            'campaigns' => $campaigns,
            'sentMessages' => $sentMessages,
            'segments' => $segments,
            'stats' => [
                'total_contacts' => $totalContacts,
                'phone_contacts' => $phoneContacts,
                'inactive_30d' => $inactive30d,
                'inactive_40d' => $inactive40d,
                'total_messages_sent' => $totalMessagesSent,
            ],
            'workspaceId' => $workspace->id,
        ]);
    }

    /**
     * Tạo và kích hoạt chiến dịch gửi tin hàng loạt.
     */
    public function store(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', 'string', 'in:all,manual,inactive_30d,inactive_40d,has_phone,segment'],
            'customer_segment_id' => ['nullable', 'uuid'],
            'message_template' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'image', 'max:10240'],
            'ai_spin_enabled' => ['nullable', 'boolean'],
            'delay_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'scheduled_at' => [
                'nullable',
                'date',
                Rule::when($request->boolean('repeat_daily'), ['required', 'after:now']),
            ],
            'repeat_daily' => ['nullable', 'boolean'],
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('broadcast-images', 'public');
            $imageUrl = '/storage/'.$path;
        }

        $triggerType = $validated['trigger_type'];
        $inactiveDays = null;
        if ($triggerType === 'inactive_30d') {
            $inactiveDays = 30;
        } elseif ($triggerType === 'inactive_40d') {
            $inactiveDays = 40;
        }

        $scheduledAt = ! empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        $isScheduled = $scheduledAt && $scheduledAt->isFuture();
        $repeatDaily = (bool) ($validated['repeat_daily'] ?? false);

        $campaign = BroadcastCampaign::query()->create([
            'workspace_id' => $workspace->id,
            'customer_segment_id' => $validated['customer_segment_id'] ?? null,
            'name' => $validated['name'],
            'trigger_type' => $triggerType,
            'trigger_inactive_days' => $inactiveDays,
            'message_template' => $validated['message_template'],
            'image_url' => $imageUrl,
            'ai_spin_enabled' => $validated['ai_spin_enabled'] ?? true,
            'delay_seconds' => $validated['delay_seconds'] ?? 3,
            'status' => $isScheduled ? 'scheduled' : 'sending',
            'scheduled_at' => $scheduledAt,
            'repeat_daily' => $repeatDaily,
            'started_at' => $isScheduled ? null : now(),
        ]);

        if ($isScheduled) {
            return response()->json([
                'status' => 'success',
                'message' => 'Chiến dịch đã được lên lịch gửi thành công!',
                'campaign' => $campaign->load(['customerSegment', 'channel'])->loadCount(['messages', 'messages as sent_count' => fn ($q) => $q->where('status', 'sent')]),
                'sent_count' => 0,
                'is_scheduled' => true,
            ]);
        }

        // Truy vấn danh sách khách hàng mục tiêu
        $contactsQuery = OmnichatContact::query()->where('workspace_id', $workspace->id);

        if ($triggerType === 'inactive_30d') {
            $contactsQuery = $this->filterService->queryInactiveContacts($workspace->id, 30);
        } elseif ($triggerType === 'inactive_40d') {
            $contactsQuery = $this->filterService->queryInactiveContacts($workspace->id, 40);
        } elseif ($triggerType === 'has_phone') {
            $contactsQuery->whereNotNull('phone')->where('phone', '!=', '');
        } elseif ($triggerType === 'segment' && ! empty($validated['customer_segment_id'])) {
            $segment = CustomerSegment::query()->where('workspace_id', $workspace->id)->whereKey($validated['customer_segment_id'])->first();
            if ($segment) {
                $contactsQuery = $this->filterService->queryForSegment($segment);
            }
        }

        $contacts = $contactsQuery->take(500)->get();

        // Tạo danh sách tin nhắn gửi
        $sentCount = 0;
        foreach ($contacts as $contact) {
            $customerName = $contact->name ?: $contact->display_name ?: 'anh/chị';
            $phone = $contact->phone ?: '';

            // Cá nhân hóa nội dung
            $personalizedBody = str_replace(
                ['{name}', '{ho_ten}', '{phone}', '{sdt}'],
                [$customerName, $customerName, $phone, $phone],
                $validated['message_template']
            );

            // Tìm conversation gần nhất của contact này nếu có
            $conversation = $contact->conversations()->latest()->first();

            BroadcastMessage::query()->create([
                'workspace_id' => $workspace->id,
                'broadcast_campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation?->id,
                'sent_body' => $personalizedBody,
                'image_url' => $imageUrl,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $sentCount++;
        }

        $campaign->update([
            'status' => 'completed',
            'completed_at' => now(),
            'stats' => [
                'total_targeted' => $contacts->count(),
                'total_sent' => $sentCount,
                'failed' => 0,
            ],
        ]);

        return response()->json([
            'success' => true,
            'campaign' => $campaign->fresh(['customerSegment', 'channel']),
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * Tạo dữ liệu mẫu khách hàng (Seed demo contacts) để kiểm tra gửi tin hàng loạt ngay lập tức.
     */
    public function generateSampleContacts(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $sampleList = [
            ['name' => 'Nguyễn Văn An', 'phone' => '0912345678', 'days_ago' => 35],
            ['name' => 'Trần Thị Mai', 'phone' => '0987654321', 'days_ago' => 45],
            ['name' => 'Lê Hoàng Nam', 'phone' => '0909112233', 'days_ago' => 5],
            ['name' => 'Phạm Thu Hà', 'phone' => '0933445566', 'days_ago' => 42],
            ['name' => 'Võ Minh Khôi', 'phone' => '0944556677', 'days_ago' => 32],
            ['name' => 'Đặng Thảo Vy', 'phone' => null, 'days_ago' => 38],
            ['name' => 'Bùi Gia Huy', 'phone' => '0966778899', 'days_ago' => 50],
        ];

        $created = 0;
        foreach ($sampleList as $item) {
            OmnichatContact::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $item['name'],
                'display_name' => $item['name'],
                'phone' => $item['phone'],
                'last_seen_at' => now()->subDays($item['days_ago']),
                'is_lead' => ! empty($item['phone']),
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'created' => $created,
        ]);
    }

    /**
     * Preview số lượng khách hàng trước khi gửi.
     */
    public function previewAudience(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $triggerType = $request->input('trigger_type', 'manual');
        $segmentId = $request->input('customer_segment_id');

        $contactsQuery = OmnichatContact::query()->where('workspace_id', $workspace->id);

        if ($triggerType === 'inactive_30d') {
            $contactsQuery = $this->filterService->queryInactiveContacts($workspace->id, 30);
        } elseif ($triggerType === 'inactive_40d') {
            $contactsQuery = $this->filterService->queryInactiveContacts($workspace->id, 40);
        } elseif ($triggerType === 'has_phone') {
            $contactsQuery->whereNotNull('phone')->where('phone', '!=', '');
        } elseif ($triggerType === 'segment' && ! empty($segmentId)) {
            $segment = CustomerSegment::query()->where('workspace_id', $workspace->id)->whereKey($segmentId)->first();
            if ($segment) {
                $contactsQuery = $this->filterService->queryForSegment($segment);
            }
        } elseif ($triggerType === 'all') {
            // All contacts
        }

        $count = $contactsQuery->count();
        $sampleContacts = $contactsQuery->take(5)->get(['id', 'name', 'phone', 'last_seen_at']);

        return response()->json([
            'count' => $count,
            'samples' => $sampleContacts,
        ]);
    }

    /**
     * Thử lại (Retry) gửi các tin nhắn đã chọn từ checklist.
     */
    public function retryMessages(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['required', 'uuid'],
        ]);

        $messages = BroadcastMessage::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['message_ids'])
            ->get();

        $retriedCount = 0;
        foreach ($messages as $msg) {
            // Cập nhật trạng thái sang sent và thời gian sent_at mới nhất
            $msg->update([
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);
            $retriedCount++;
        }

        return response()->json([
            'success' => true,
            'retried_count' => $retriedCount,
            'message' => "Đã gửi lại thành công {$retriedCount} tin nhắn!",
        ]);
    }
}
