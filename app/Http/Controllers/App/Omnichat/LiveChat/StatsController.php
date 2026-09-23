<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Services\Omnichat\OmnichatAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatsController extends Controller
{
    public function __construct(private readonly OmnichatAnalyticsService $analytics) {}

    /**
     * Real statistics for the mobile LiveChat "Thống kê" tab.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month'])],
        ]);

        return response()->json(
            $this->analytics->liveChatOverview($workspace->id, (string) ($validated['period'] ?? 'today')),
        );
    }
}
