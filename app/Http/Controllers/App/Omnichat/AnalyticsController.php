<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Omnichat\OmnichatAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly OmnichatAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('viewAnalytics', $workspace);

        $filters = [
            'period' => (string) $request->input('period', '7d'),
            'search' => (string) $request->input('search', ''),
            'channel_id' => (string) $request->input('channel_id', 'all'),
            'status' => (string) $request->input('status', 'all'),
            'lead_status' => (string) $request->input('lead_status', 'all'),
            'assignee_id' => (string) $request->input('assignee_id', 'all'),
            'ai_mode' => (string) $request->input('ai_mode', 'all'),
            'from' => (string) $request->input('from', now()->subDays(6)->startOfDay()->toDateTimeString()),
            'to' => (string) $request->input('to', now()->endOfDay()->toDateTimeString()),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 10),
        ];

        return Inertia::render('omnichat/Analytics', [
            'workspace' => $workspace,
            ...$this->analytics->index($workspace->id, $filters),
        ]);
    }

    public function userShow(Request $request, User $user): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('viewAnalytics', $workspace);

        $filters = [
            'search' => (string) $request->input('search', ''),
            'status' => (string) $request->input('status', 'all'),
            'date_range' => (string) $request->input('date_range', '7d'),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 10),
        ];

        $data = $this->analytics->getUserAnalyticsData($workspace->id, $user, $filters);

        return Inertia::render('omnichat/UserAnalytics', [
            'workspace' => $workspace,
            ...$data,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('viewAnalytics', $workspace);

        $filters = [
            'period' => (string) $request->input('period', '7d'),
            'search' => (string) $request->input('search', ''),
            'channel_id' => (string) $request->input('channel_id', 'all'),
            'status' => (string) $request->input('status', 'all'),
            'lead_status' => (string) $request->input('lead_status', 'all'),
            'assignee_id' => (string) $request->input('assignee_id', 'all'),
            'ai_mode' => (string) $request->input('ai_mode', 'all'),
            'from' => (string) $request->input('from', now()->subDays(6)->startOfDay()->toDateTimeString()),
            'to' => (string) $request->input('to', now()->endOfDay()->toDateTimeString()),
        ];

        $data = $this->analytics->index($workspace->id, $filters);
        $contacts = $data['contacts']['data'] ?? [];

        return response()->streamDownload(function () use ($contacts) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Mã KH', 'Tên khách hàng', 'SĐT', 'Email', 'Kênh / Page', 'Trạng thái', 'Phân loại Lead', 'Chế độ AI', 'Tin nhắn cuối', 'Đánh giá CSAT']);

            foreach ($contacts as $c) {
                fputcsv($handle, [
                    $c['id'],
                    $c['name'],
                    $c['phone'] ?? '',
                    $c['email'] ?? '',
                    $c['page_name'],
                    $c['status'],
                    $c['lead_status'],
                    $c['ai_status'],
                    $c['last_message'] ?? '',
                    $c['csat_score'] ?? 5,
                ]);
            }

            fclose($handle);
        }, 'omnichat-analytics-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
