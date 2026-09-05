<?php

declare(strict_types=1);

namespace App\Services\Omnichat;

use App\Models\OmnichatChannel;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OmnichatAnalyticsService
{
    /** @return array<string, mixed> */
    public function index(string $workspaceId, array $filters): array
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $accounts = SocialAccount::query()->where('workspace_id', $workspaceId)->where('is_active', true)->get();
        $websiteChannels = OmnichatChannel::query()->where('workspace_id', $workspaceId)->get();
        $members = $workspace->members()->get();

        $period = (string) ($filters['period'] ?? '7d');

        [$startDate, $endDate] = match ($period) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            '30d' => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()],
            '90d' => [Carbon::now()->subDays(89)->startOfDay(), Carbon::now()->endOfDay()],
            'custom' => [
                ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->subDays(6)->startOfDay(),
                ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : Carbon::now()->endOfDay(),
            ],
            default => [
                ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->subDays(6)->startOfDay(),
                ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : Carbon::now()->endOfDay(),
            ],
        };

        $from = $startDate->toDateTimeString();
        $to = $endDate->toDateTimeString();

        $search = trim((string) ($filters['search'] ?? ''));
        $channelId = (string) ($filters['channel_id'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');
        $leadStatus = (string) ($filters['lead_status'] ?? 'all');
        $assigneeId = (string) ($filters['assignee_id'] ?? 'all');
        $aiMode = (string) ($filters['ai_mode'] ?? 'all');
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = max((int) ($filters['per_page'] ?? 10), 5);

        // 1. Real Summary KPI calculations
        $summary = $this->calculateRealSummary($workspaceId, $accounts, $websiteChannels, $from, $to, $channelId, $assigneeId);

        // 2. Real Channel / Page metrics (Social Accounts + Website Chat Channels)
        $channelMetrics = $this->calculateRealChannelMetrics($workspaceId, $accounts, $websiteChannels, $from, $to);

        // 3. Real Contacts List with full Search and Filter
        $contactsData = $this->getRealContactsList($workspaceId, [
            'search' => $search,
            'channel_id' => $channelId,
            'status' => $status,
            'lead_status' => $leadStatus,
            'assignee_id' => $assigneeId,
            'ai_mode' => $aiMode,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        // 4. Real Sales / Staff performance
        $salesMetrics = $this->calculateRealSalesSummary($workspaceId, $members, $from, $to);

        // 5. Real Trend over time
        $trend = $this->calculateRealTrend($workspaceId, $from, $to, $channelId, $assigneeId);

        $socialOptions = $accounts->map(fn (SocialAccount $a): array => [
            'id' => $a->id,
            'name' => $a->display_name ?: $a->platform->label(),
            'platform' => $a->platform->value,
            'avatar_url' => $a->avatar_url,
            'ai_enabled' => (bool) ($a->meta['ai_care']['enabled'] ?? false),
        ]);

        $websiteOptions = $websiteChannels->map(fn (OmnichatChannel $c): array => [
            'id' => $c->id,
            'name' => $c->name ?: ($c->platform === 'website_chat' ? 'Website Live Chat' : strtoupper($c->platform)),
            'platform' => $c->platform,
            'avatar_url' => null,
            'ai_enabled' => (bool) ($c->meta['ai_care']['enabled'] ?? false),
        ]);

        return [
            'summary' => $summary,
            'channels' => $channelMetrics,
            'contacts' => $contactsData,
            'sales' => $salesMetrics,
            'trend' => $trend,
            'channelsOptions' => $socialOptions->concat($websiteOptions)->values()->all(),
            'salesOptions' => $members->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_url ?? null,
            ])->values()->all(),
            'filters' => array_merge($filters, [
                'period' => $period,
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
                'search' => $search,
                'channel_id' => $channelId,
                'status' => $status,
                'lead_status' => $leadStatus,
                'assignee_id' => $assigneeId,
                'ai_mode' => $aiMode,
                'per_page' => $perPage,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function getUserAnalyticsData(string $workspaceId, User $user, array $filters): array
    {
        $dateRange = (string) ($filters['date_range'] ?? '7d');

        [$startDate, $endDate, $isSingleDay] = match ($dateRange) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), true],
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay(), true],
            '30d' => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay(), false],
            '90d' => [Carbon::now()->subDays(89)->startOfDay(), Carbon::now()->endOfDay(), false],
            'custom' => [
                ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->subDays(6)->startOfDay(),
                ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : Carbon::now()->endOfDay(),
                ! empty($filters['from']) && ! empty($filters['to']) && Carbon::parse($filters['from'])->isSameDay(Carbon::parse($filters['to'])),
            ],
            default => [
                ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : Carbon::now()->subDays(6)->startOfDay(),
                ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : Carbon::now()->endOfDay(),
                false,
            ],
        };

        $fromStr = $startDate->toDateTimeString();
        $toStr = $endDate->toDateTimeString();

        // 1. User conversations and messages in period
        $conversationsQuery = OmnichatConversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('assigned_user_id', $user->id);

        $totalAssigned = (clone $conversationsQuery)->count();
        $resolvedCount = (clone $conversationsQuery)->whereIn('status', ['resolved', 'closed'])->count();
        $activeCount = (clone $conversationsQuery)->whereNotIn('status', ['resolved', 'closed'])->count();

        $messagesSent = OmnichatMessage::query()
            ->where('workspace_id', $workspaceId)
            ->where('sender_user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$fromStr, $toStr])
            ->count();

        // AI handed off count: conversations assigned to this user where AI also participated
        $aiCollabCount = OmnichatConversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('assigned_user_id', $user->id)
            ->whereHas('messages', fn ($q) => $q->where('direction', 'outbound')->whereNull('sender_user_id'))
            ->count();

        $resolutionRate = $totalAssigned > 0 ? round(($resolvedCount / $totalAssigned) * 100, 1) : 0.0;

        $userAvgSeconds = $this->calculateRealResponseSeconds($workspaceId, null, $user->id, $fromStr, $toStr);
        $avgMinutes = round($userAvgSeconds / 60, 1);

        // 2. Real Productivity Trend
        $productivityTrend = [];
        if ($isSingleDay) {
            // For single day (Today or Yesterday), break down into 2-hour slots for a rich, visual chart
            $slots = [
                ['08:00 - 10:00', 8, 10],
                ['10:00 - 12:00', 10, 12],
                ['12:00 - 14:00', 12, 14],
                ['14:00 - 16:00', 14, 16],
                ['16:00 - 18:00', 16, 18],
                ['18:00 - 20:00', 18, 20],
                ['20:00 - 22:00', 20, 22],
            ];

            foreach ($slots as [$label, $startH, $endH]) {
                $slotStart = (clone $startDate)->setTime($startH, 0, 0);
                $slotEnd = (clone $startDate)->setTime($endH - 1, 59, 59);

                $convs = OmnichatConversation::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('assigned_user_id', $user->id)
                    ->whereBetween('updated_at', [$slotStart, $slotEnd])
                    ->count();

                $msgs = OmnichatMessage::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('sender_user_id', $user->id)
                    ->whereBetween('created_at', [$slotStart, $slotEnd])
                    ->count();

                $resolved = OmnichatConversation::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('assigned_user_id', $user->id)
                    ->whereIn('status', ['resolved', 'closed'])
                    ->whereBetween('updated_at', [$slotStart, $slotEnd])
                    ->count();

                $productivityTrend[] = [
                    'date' => $label,
                    'display_date' => $label,
                    'conversations' => $convs,
                    'messages' => $msgs,
                    'resolved' => $resolved,
                    'avg_response_minutes' => $avgMinutes,
                ];
            }
        } else {
            // Multi-day view: Point per calendar day
            $period = CarbonPeriod::create($startDate->copy()->startOfDay(), '1 day', $endDate->copy()->startOfDay());

            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();
                $dateStr = $date->format('Y-m-d');
                $displayDate = $date->format('d/m');

                $convs = OmnichatConversation::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('assigned_user_id', $user->id)
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->count();

                $msgs = OmnichatMessage::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('sender_user_id', $user->id)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count();

                $resolved = OmnichatConversation::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('assigned_user_id', $user->id)
                    ->whereIn('status', ['resolved', 'closed'])
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->count();

                $productivityTrend[] = [
                    'date' => $dateStr,
                    'display_date' => $displayDate,
                    'conversations' => $convs,
                    'messages' => $msgs,
                    'resolved' => $resolved,
                    'avg_response_minutes' => $avgMinutes,
                ];
            }
        }

        // 3. Real Hourly Distribution of messages sent by user (8:00 - 22:00)
        $hourlyDistribution = [];
        $userMessages = OmnichatMessage::query()
            ->where('workspace_id', $workspaceId)
            ->where('sender_user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$fromStr, $toStr])
            ->get(['created_at', 'sent_at']);

        $groupedHours = $userMessages->groupBy(fn ($m) => (int) ($m->sent_at ? $m->sent_at->format('H') : $m->created_at->format('H')));

        for ($hour = 8; $hour <= 22; $hour++) {
            $hourlyDistribution[] = [
                'hour' => sprintf('%02d:00', $hour),
                'messages' => count($groupedHours->get($hour, [])),
            ];
        }

        // 4. Real Assigned Customers List with pagination
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = max((int) ($filters['per_page'] ?? 10), 5);

        $assignedContacts = $this->getRealContactsList($workspaceId, [
            'search' => (string) ($filters['search'] ?? ''),
            'status' => (string) ($filters['status'] ?? 'all'),
            'assignee_id' => $user->id,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'user_summary' => [
                'total_assigned' => $totalAssigned,
                'total_messages_sent' => $messagesSent,
                'resolved_count' => $resolvedCount,
                'resolution_rate' => $resolutionRate,
                'avg_response_minutes' => $avgMinutes,
                'avg_response_display' => $this->formatResponseTime($userAvgSeconds),
                'csat_avg' => 5.0,
                'ai_collaboration_count' => $aiCollabCount,
                'active_customers_count' => $activeCount,
            ],
            'productivity_trend' => $productivityTrend,
            'hourly_distribution' => $hourlyDistribution,
            'assigned_customers' => $assignedContacts,
            'filters' => array_merge($filters, [
                'date_range' => $dateRange,
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
                'is_single_day' => $isSingleDay,
                'per_page' => $perPage,
                'page' => $page,
            ]),
        ];
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     * @param  Collection<int, OmnichatChannel>  $websiteChannels
     * @return array<string, mixed>
     */
    private function calculateRealSummary(string $workspaceId, Collection $accounts, Collection $websiteChannels, string $from, string $to, string $channelId, string $assigneeId): array
    {
        $messagesQuery = OmnichatMessage::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$from, $to]);

        $convsQuery = OmnichatConversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('updated_at', [$from, $to]);

        if ($channelId !== 'all') {
            $messagesQuery->where(function ($q) use ($channelId) {
                $q->where('social_account_id', $channelId)
                    ->orWhere('channel_id', $channelId);
            });
            $convsQuery->where(function ($q) use ($channelId) {
                $q->where('social_account_id', $channelId)
                    ->orWhere('channel_id', $channelId);
            });
        }

        if ($assigneeId !== 'all') {
            if ($assigneeId === 'unassigned') {
                $convsQuery->whereNull('assigned_user_id');
                $messagesQuery->whereHas('conversation', fn ($c) => $c->whereNull('assigned_user_id'));
            } else {
                $convsQuery->where('assigned_user_id', $assigneeId);
                $messagesQuery->whereHas('conversation', fn ($c) => $c->where('assigned_user_id', $assigneeId));
            }
        }

        $totalMessages = (clone $messagesQuery)->count();
        $inbound = (clone $messagesQuery)->where('direction', 'inbound')->count();
        $outbound = (clone $messagesQuery)->where('direction', 'outbound')->count();
        $aiOutbound = (clone $messagesQuery)->where('direction', 'outbound')->whereNull('sender_user_id')->count();

        $totalConversations = (clone $convsQuery)->count();
        $resolvedConversations = (clone $convsQuery)->whereIn('status', ['resolved', 'closed'])->count();

        $uniqueContacts = OmnichatContact::query()->where('workspace_id', $workspaceId)->count();
        $hotLeads = OmnichatContact::query()->where('workspace_id', $workspaceId)->where('is_lead', true)->count();
        $activePagesCount = $accounts->count() + $websiteChannels->count();

        // Check if ANY active page or channel in this workspace actually has AI enabled
        $anyAiEnabled = $accounts->contains(fn ($acc) => (bool) ($acc->meta['ai_care']['enabled'] ?? false))
            || $websiteChannels->contains(fn ($ch) => (bool) ($ch->meta['ai_care']['enabled'] ?? false));

        $aiHandledRate = ($anyAiEnabled && $outbound > 0) ? round(($aiOutbound / $outbound) * 100, 1) : 0.0;
        $resolvedRate = $totalConversations > 0 ? round(($resolvedConversations / $totalConversations) * 100, 1) : 0.0;

        $avgSec = $this->calculateRealResponseSeconds($workspaceId, $channelId !== 'all' ? $channelId : null, null, $from, $to);

        return [
            'messages' => $totalMessages,
            'conversations' => $totalConversations,
            'contacts' => $uniqueContacts,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'ai_handled_rate' => $aiHandledRate,
            'ai_enabled' => $anyAiEnabled,
            'resolved_rate' => $resolvedRate,
            'avg_response_display' => $this->formatResponseTime($avgSec),
            'hot_leads_count' => $hotLeads,
            'connected_pages_count' => $activePagesCount,
        ];
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     * @param  Collection<int, OmnichatChannel>  $websiteChannels
     * @return array<int, array<string, mixed>>
     */
    private function calculateRealChannelMetrics(string $workspaceId, Collection $accounts, Collection $websiteChannels, string $from, string $to): array
    {
        $socialMetrics = $accounts->map(function (SocialAccount $account) use ($workspaceId, $from, $to) {
            $meta = $account->meta['ai_care'] ?? [];
            $isAiEnabled = (bool) ($meta['enabled'] ?? false);

            $convs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('social_account_id', $account->id)
                ->count();

            $messagesQuery = OmnichatMessage::query()
                ->where('workspace_id', $workspaceId)
                ->where('social_account_id', $account->id);

            $totalMsgs = (clone $messagesQuery)->count();
            $inbound = (clone $messagesQuery)->where('direction', 'inbound')->count();
            $outbound = (clone $messagesQuery)->where('direction', 'outbound')->count();
            $aiOutbound = (clone $messagesQuery)->where('direction', 'outbound')->whereNull('sender_user_id')->count();

            $resolvedConvs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('social_account_id', $account->id)
                ->whereIn('status', ['resolved', 'closed'])
                ->count();

            $aiRate = ($isAiEnabled && $outbound > 0) ? round(($aiOutbound / $outbound) * 100, 1) : 0.0;
            $resRate = $convs > 0 ? round(($resolvedConvs / $convs) * 100, 1) : 0.0;
            $avgSec = $this->calculateRealResponseSeconds($workspaceId, $account->id, null, $from, $to);

            return [
                'account_id' => $account->id,
                'display_name' => $account->display_name ?: $account->platform->label(),
                'username' => $account->username,
                'platform' => $account->platform->value,
                'avatar_url' => $account->avatar_url,
                'ai_care_enabled' => $isAiEnabled,
                'bot_name' => $isAiEnabled ? ($meta['bot_name'] ?? ('AI '.($account->display_name ?: $account->platform->label()))) : 'Chưa bật AI',
                'schedule_mode' => $isAiEnabled ? ($meta['operating_hours']['mode'] ?? '24/7') : 'Tắt',
                'total_conversations' => $convs,
                'total_messages' => $totalMsgs,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'ai_handled_rate' => $aiRate,
                'resolved_rate' => $resRate,
                'avg_response_seconds' => $avgSec,
            ];
        });

        $channelMetrics = $websiteChannels->map(function (OmnichatChannel $channel) use ($workspaceId, $from, $to) {
            $meta = $channel->meta['ai_care'] ?? [];
            $isAiEnabled = (bool) ($meta['enabled'] ?? false);

            $convs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel_id', $channel->id)
                ->count();

            $messagesQuery = OmnichatMessage::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel_id', $channel->id);

            $totalMsgs = (clone $messagesQuery)->count();
            $inbound = (clone $messagesQuery)->where('direction', 'inbound')->count();
            $outbound = (clone $messagesQuery)->where('direction', 'outbound')->count();
            $aiOutbound = (clone $messagesQuery)->where('direction', 'outbound')->whereNull('sender_user_id')->count();

            $resolvedConvs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel_id', $channel->id)
                ->whereIn('status', ['resolved', 'closed'])
                ->count();

            $aiRate = ($isAiEnabled && $outbound > 0) ? round(($aiOutbound / $outbound) * 100, 1) : 0.0;
            $resRate = $convs > 0 ? round(($resolvedConvs / $convs) * 100, 1) : 0.0;
            $avgSec = $this->calculateRealResponseSeconds($workspaceId, $channel->id, null, $from, $to);

            return [
                'account_id' => $channel->id,
                'display_name' => $channel->name ?: ($channel->platform === 'website_chat' ? 'Website Live Chat' : strtoupper($channel->platform)),
                'username' => null,
                'platform' => $channel->platform,
                'avatar_url' => null,
                'ai_care_enabled' => $isAiEnabled,
                'bot_name' => $isAiEnabled ? ($meta['bot_name'] ?? 'AI Chatbot') : 'Chưa bật AI',
                'schedule_mode' => $isAiEnabled ? ($meta['operating_hours']['mode'] ?? '24/7') : 'Tắt',
                'total_conversations' => $convs,
                'total_messages' => $totalMsgs,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'ai_handled_rate' => $aiRate,
                'resolved_rate' => $resRate,
                'avg_response_seconds' => $avgSec,
            ];
        });

        return $socialMetrics->concat($channelMetrics)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function getRealContactsList(string $workspaceId, array $filters): array
    {
        $query = OmnichatContact::query()
            ->where('workspace_id', $workspaceId)
            ->with([
                'conversations' => fn ($q) => $q->latest('last_message_at')->with(['socialAccount', 'assignedUser', 'tags']),
            ]);

        // Search by keyword
        if (! empty($filters['search'])) {
            $s = (string) $filters['search'];
            $query->where(function (EloquentBuilder $q) use ($s) {
                $q->where('display_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('notes', 'like', "%{$s}%")
                    ->orWhereHas('conversations', fn ($c) => $c->where('last_message_preview', 'like', "%{$s}%"));
            });
        }

        // Filter by channel / page
        if (! empty($filters['channel_id']) && $filters['channel_id'] !== 'all') {
            $query->whereHas('conversations', fn ($c) => $c->where('social_account_id', $filters['channel_id']));
        }

        // Filter by status
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->whereHas('conversations', fn ($c) => $c->where('status', $filters['status']));
        }

        // Filter by lead status
        if (! empty($filters['lead_status']) && $filters['lead_status'] !== 'all') {
            if ($filters['lead_status'] === 'hot') {
                $query->where('is_lead', true);
            } else {
                $query->where('lead_stage', $filters['lead_status']);
            }
        }

        // Filter by assignee user
        if (! empty($filters['assignee_id']) && $filters['assignee_id'] !== 'all') {
            if ($filters['assignee_id'] === 'unassigned') {
                $query->whereHas('conversations', fn ($c) => $c->whereNull('assigned_user_id'));
            } else {
                $query->whereHas('conversations', fn ($c) => $c->where('assigned_user_id', $filters['assignee_id']));
            }
        }

        // Filter by AI Mode
        if (! empty($filters['ai_mode']) && $filters['ai_mode'] !== 'all') {
            if ($filters['ai_mode'] === 'ai') {
                $query->whereHas('conversations', fn ($c) => $c->whereNull('assigned_user_id')->whereHas('socialAccount', fn ($sa) => $sa->where('meta->ai_care->enabled', true)));
            } elseif ($filters['ai_mode'] === 'human') {
                $query->whereHas('conversations', fn ($c) => $c->whereNotNull('assigned_user_id'));
            } elseif ($filters['ai_mode'] === 'disabled') {
                $query->whereHas('conversations', fn ($c) => $c->whereNull('assigned_user_id'));
            }
        }

        $perPage = max((int) ($filters['per_page'] ?? 10), 5);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $paginator = $query->orderByDesc('last_seen_at')->orderByDesc('updated_at')->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function (OmnichatContact $contact): array {
            /** @var OmnichatConversation|null $conv */
            $conv = $contact->conversations->first();
            $social = $conv?->socialAccount;
            $assigned = $conv?->assignedUser;

            $tags = $conv ? $conv->tags->pluck('name')->all() : [];

            // Check if page really has AI enabled
            $isAiEnabled = (bool) ($social?->meta['ai_care']['enabled'] ?? false);

            if ($isAiEnabled) {
                $aiStatus = $assigned ? 'handed_off' : 'active';
            } else {
                $aiStatus = $assigned ? 'human_only' : 'disabled';
            }

            $lastMsg = $conv?->last_message_preview ?: 'Chưa có tin nhắn';
            $lastAt = $conv?->last_message_at ?: $contact->last_seen_at ?: $contact->updated_at;

            return [
                'id' => (string) $contact->id,
                'name' => $contact->display_name ?: 'Khách hàng',
                'avatar_url' => $contact->avatar_url ?: ('https://api.dicebear.com/7.x/avataaars/svg?seed='.urlencode($contact->display_name ?: $contact->id)),
                'email' => $contact->email,
                'phone' => $contact->phone,
                'platform' => $social?->platform?->value ?? 'facebook',
                'page_id' => $social?->id ?? '',
                'page_name' => $social?->display_name ?: ($social?->platform?->label() ?? 'Chưa liên kết'),
                'status' => $conv?->status ?? 'open',
                'lead_status' => $contact->is_lead ? ($contact->lead_stage ?: 'hot') : ($contact->lead_stage ?: 'cold'),
                'ai_status' => $aiStatus,
                'assigned_user' => $assigned ? [
                    'id' => $assigned->id,
                    'name' => $assigned->name,
                    'avatar_url' => $assigned->avatar_url ?? null,
                ] : null,
                'last_message' => $lastMsg,
                'last_message_at' => $lastAt?->toIso8601String() ?? now()->toIso8601String(),
                'last_message_display' => $lastAt?->diffForHumans() ?? 'Vừa xong',
                'total_messages' => $conv ? $conv->messages()->count() : 0,
                'csat_score' => 5,
                'tags' => $tags ?: ($contact->is_lead ? ['Tiềm năng'] : ['Khách mới']),
                'notes' => $contact->notes ?: 'Chưa có ghi chú.',
            ];
        })->all();

        return [
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => max($paginator->lastPage(), 1),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'links' => $paginator->linkCollection()->toArray(),
        ];
    }

    /**
     * @param  Collection<int, User>  $members
     * @return array<int, array<string, mixed>>
     */
    private function calculateRealSalesSummary(string $workspaceId, Collection $members, string $from, string $to): array
    {
        return $members->map(function (User $user) use ($workspaceId, $from, $to): array {
            $assignedConvs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('assigned_user_id', $user->id)
                ->count();

            $resolvedConvs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', ['resolved', 'closed'])
                ->count();

            $messagesSent = OmnichatMessage::query()
                ->where('workspace_id', $workspaceId)
                ->where('sender_user_id', $user->id)
                ->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$from, $to])
                ->count();

            $aiAssistedConvs = OmnichatConversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('assigned_user_id', $user->id)
                ->whereHas('messages', fn ($q) => $q->where('direction', 'outbound')->whereNull('sender_user_id'))
                ->count();

            $resRate = $assignedConvs > 0 ? round(($resolvedConvs / $assignedConvs) * 100, 1) : 0.0;
            $aiCollabRate = $assignedConvs > 0 ? round(($aiAssistedConvs / $assignedConvs) * 100, 1) : 0.0;
            $userAvgSec = $this->calculateRealResponseSeconds($workspaceId, null, $user->id, $from, $to);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
                'assigned_conversations' => $assignedConvs,
                'messages_sent' => $messagesSent,
                'resolved_conversations' => $resolvedConvs,
                'resolution_rate' => $resRate,
                'avg_response_minutes' => round($userAvgSec / 60, 1),
                'csat_avg' => 5.0,
                'ai_collaboration_rate' => $aiCollabRate,
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function calculateRealTrend(string $workspaceId, string $from, string $to, string $channelId, string $assigneeId): array
    {
        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->endOfDay();
        $isSingleDay = $startDate->isSameDay($endDate);

        $messagesQuery = OmnichatMessage::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$from, $to]);

        if ($channelId !== 'all') {
            $messagesQuery->where('social_account_id', $channelId);
        }

        if ($assigneeId !== 'all') {
            if ($assigneeId === 'unassigned') {
                $messagesQuery->whereHas('conversation', fn ($c) => $c->whereNull('assigned_user_id'));
            } else {
                $messagesQuery->whereHas('conversation', fn ($c) => $c->where('assigned_user_id', $assigneeId));
            }
        }

        $allMessages = $messagesQuery->get(['id', 'conversation_id', 'direction', 'sender_user_id', 'sent_at', 'created_at']);

        $trends = [];

        if ($isSingleDay) {
            // For single day view (e.g. today), output 2-hour interval slots
            $slots = [
                ['08:00', 8, 10],
                ['10:00', 10, 12],
                ['12:00', 12, 14],
                ['14:00', 14, 16],
                ['16:00', 16, 18],
                ['18:00', 18, 20],
                ['20:00', 20, 22],
            ];

            foreach ($slots as [$label, $startH, $endH]) {
                $slotMessages = $allMessages->filter(function ($m) use ($startH, $endH) {
                    $dt = $m->sent_at ?: $m->created_at;
                    $h = (int) $dt->format('H');

                    return $h >= $startH && $h < $endH;
                });

                $inbound = $slotMessages->where('direction', 'inbound')->count();
                $outbound = $slotMessages->where('direction', 'outbound')->count();
                $aiReplies = $slotMessages->where('direction', 'outbound')->whereNull('sender_user_id')->count();
                $humanReplies = $slotMessages->where('direction', 'outbound')->whereNotNull('sender_user_id')->count();
                $convs = $slotMessages->pluck('conversation_id')->filter()->unique()->count();

                $trends[] = [
                    'date' => $label,
                    'display_date' => $label,
                    'messages' => $slotMessages->count(),
                    'conversations' => $convs,
                    'inbound' => $inbound,
                    'outbound' => $outbound,
                    'ai_replies' => $aiReplies,
                    'human_replies' => $humanReplies,
                ];
            }
        } else {
            $period = CarbonPeriod::create($startDate, '1 day', $endDate);

            foreach ($period as $date) {
                $currDate = $date->format('Y-m-d');
                $displayDate = $date->format('d/m');

                $dayMessages = $allMessages->filter(function ($m) use ($currDate) {
                    $msgDate = $m->sent_at ? $m->sent_at->format('Y-m-d') : $m->created_at->format('Y-m-d');

                    return $msgDate === $currDate;
                });

                $inbound = $dayMessages->where('direction', 'inbound')->count();
                $outbound = $dayMessages->where('direction', 'outbound')->count();
                $aiReplies = $dayMessages->where('direction', 'outbound')->whereNull('sender_user_id')->count();
                $humanReplies = $dayMessages->where('direction', 'outbound')->whereNotNull('sender_user_id')->count();
                $convs = $dayMessages->pluck('conversation_id')->filter()->unique()->count();

                $trends[] = [
                    'date' => $currDate,
                    'display_date' => $displayDate,
                    'messages' => $dayMessages->count(),
                    'conversations' => $convs,
                    'inbound' => $inbound,
                    'outbound' => $outbound,
                    'ai_replies' => $aiReplies,
                    'human_replies' => $humanReplies,
                ];
            }
        }

        return $trends;
    }

    private function calculateRealResponseSeconds(string $workspaceId, ?string $channelId = null, ?string $userId = null, ?string $from = null, ?string $to = null): int
    {
        $query = OmnichatConversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('last_inbound_at')
            ->whereNotNull('last_outbound_at');

        if ($channelId && $channelId !== 'all') {
            $query->where('social_account_id', $channelId);
        }

        if ($userId && $userId !== 'all') {
            $query->where('assigned_user_id', $userId);
        }

        if ($from && $to) {
            $query->whereBetween('updated_at', [$from, $to]);
        }

        $conversations = $query->get(['last_inbound_at', 'last_outbound_at']);

        if ($conversations->isEmpty()) {
            return 0;
        }

        $diffs = $conversations->map(function ($c) {
            if ($c->last_outbound_at && $c->last_inbound_at && $c->last_outbound_at >= $c->last_inbound_at) {
                return $c->last_outbound_at->diffInSeconds($c->last_inbound_at);
            }

            return null;
        })->filter(fn ($v) => $v !== null && $v > 0);

        return $diffs->isNotEmpty() ? (int) round($diffs->average()) : 0;
    }

    private function formatResponseTime(int $seconds): string
    {
        if ($seconds === 0) {
            return '0s';
        }
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        $minutes = (int) floor($seconds / 60);
        $rem = $seconds % 60;

        return $rem > 0 ? "{$minutes}m {$rem}s" : "{$minutes}m";
    }
}
