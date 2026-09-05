<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status as SocialAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\PostAnalytics\IndexRequest;
use App\Jobs\PostAnalytics\SyncAccountAnalyticsJob;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Services\Post\PostAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostAnalyticsController extends Controller
{
    public function __construct(private readonly PostAnalyticsService $analytics) {}

    public function index(IndexRequest $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $filters = $request->filters();

        $facebookPages = $workspace->socialAccounts()
            ->where('platform', Platform::Facebook)
            ->where('is_active', true)
            ->where('status', SocialAccountStatus::Connected)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'avatar_url']);

        $youtubeChannels = $workspace->socialAccounts()
            ->where('platform', Platform::YouTube)
            ->where('is_active', true)
            ->where('status', SocialAccountStatus::Connected)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'avatar_url']);

        $tiktokAccounts = $workspace->socialAccounts()
            ->where('platform', Platform::TikTok)
            ->where('is_active', true)
            ->where('status', SocialAccountStatus::Connected)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'avatar_url']);

        Tag::ensureDefaultTags($workspace);

        $workspaceTags = $workspace->tags()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('analytics/Posts', [
            'workspace' => $workspace,
            ...$this->analytics->index($workspace->id, $filters),
            'filters' => $filters,
            'dateRange' => $request->dateRange(),
            'facebookPages' => $facebookPages,
            'youtubeChannels' => $youtubeChannels,
            'tiktokAccounts' => $tiktokAccounts,
            'workspaceTags' => $workspaceTags,
        ]);
    }

    public function export(IndexRequest $request): StreamedResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $filters = $request->filters();

        // Use the service to get all rows (without pagination)
        // Since the service index() uses pagination, we can get the query directly or use the service to get all.
        // Actually, let's fetch all relevant posts and summarize them.

        $query = Post::query()
            ->where('workspace_id', $workspace->id)
            ->published()
            ->whereBetween('published_at', [$filters['from'], $filters['to']])
            ->with(['postPlatforms' => function ($query) use ($filters): void {
                $query->where('status', 'published')->with(['socialAccount', 'snapshots']);
                $query->when($filters['platform'] !== 'all', fn ($platforms) => $platforms->where('platform', $filters['platform']));
                $query->when($filters['account_id'] !== 'all', fn ($platforms) => $platforms->where('social_account_id', $filters['account_id']));
            }])
            ->latest('published_at');

        if ($filters['search'] !== '') {
            $query->where('content', 'like', "%{$filters['search']}%");
        }
        if ($filters['platform'] !== 'all') {
            $query->whereHas('postPlatforms', fn ($platforms) => $platforms
                ->where('status', 'published')
                ->where('platform', $filters['platform']));
        }
        if ($filters['account_id'] !== 'all') {
            $query->whereHas('postPlatforms', fn ($platforms) => $platforms
                ->where('status', 'published')
                ->where('social_account_id', $filters['account_id']));
        }

        if ($filters['sort'] === 'trending') {
            // we will sort later
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Output BOM for Excel to recognize UTF-8 properly
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($handle, [
                'ID Bài Viết',
                'Nội Dung',
                'Page/Nền Tảng',
                'Ngày Đăng',
                'Người Xem (Reach)',
                'Lượt Xem (Views)',
                'Tỷ Lệ Tương Tác (%)',
                'Tổng Tương Tác',
                'Lượt Thích',
                'Bình Luận',
                'Chia Sẻ',
            ]);

            $query->chunk(100, function ($posts) use ($handle) {
                foreach ($posts as $post) {
                    $summary = $this->analytics->summarize($post, false);
                    fputcsv($handle, [
                        $post->id,
                        $summary['content'],
                        implode(', ', $summary['platforms'] ?? []),
                        $summary['published_at'] ? Carbon::parse($summary['published_at'])->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                        $summary['reach'] ?? 0,
                        $summary['impressions'] ?? ($summary['views'] ?? 0),
                        $summary['engagement_rate'] ?? 0,
                        $summary['interactions'] ?? 0,
                        $summary['likes'] ?? 0,
                        $summary['comments'] ?? 0,
                        $summary['shares'] ?? 0,
                    ]);
                }
            });
            fclose($handle);
        }, 'bao-cao-bai-viet-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(Request $request, Post $post): Response
    {
        $this->authorize('viewAnalytics', $request->user()->currentWorkspace);
        $this->authorize('view', $post);

        return Inertia::render('analytics/Post', [
            'workspace' => $request->user()->currentWorkspace,
            ...$this->analytics->detail($post),
        ]);
    }

    public function syncFacebook(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);
        abort_unless($account->workspace_id === $request->user()->currentWorkspace?->id && $account->platform === Platform::Facebook, HttpResponse::HTTP_FORBIDDEN);

        Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
        SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);

        session()->flash('flash.banner', 'Quá trình đồng bộ dữ liệu đang được chạy ngầm. Vui lòng kiểm tra lại sau ít phút.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function syncAllFacebook(Request $request): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);

        $accounts = SocialAccount::query()
            ->where('workspace_id', $request->user()->currentWorkspace?->id)
            ->where('platform', Platform::Facebook)
            ->where('status', SocialAccountStatus::Connected)
            ->get();

        foreach ($accounts as $account) {
            Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
            SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);
        }

        session()->flash('flash.banner', "Đang đồng bộ {$accounts->count()} trang Facebook ngầm. Vui lòng kiểm tra lại sau ít phút.");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function syncYouTube(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);
        abort_unless($account->workspace_id === $request->user()->currentWorkspace?->id && $account->platform === Platform::YouTube, HttpResponse::HTTP_FORBIDDEN);

        Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
        SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);

        session()->flash('flash.banner', 'Quá trình đồng bộ dữ liệu đang được chạy ngầm. Vui lòng kiểm tra lại sau ít phút.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function syncAllYouTube(Request $request): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);

        $accounts = SocialAccount::query()
            ->where('workspace_id', $request->user()->currentWorkspace?->id)
            ->where('platform', Platform::YouTube)
            ->where('status', SocialAccountStatus::Connected)
            ->get();

        foreach ($accounts as $account) {
            Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
            SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);
        }

        session()->flash('flash.banner', "Đang đồng bộ {$accounts->count()} kênh YouTube ngầm. Vui lòng kiểm tra lại sau ít phút.");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function syncTikTok(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);
        abort_unless($account->workspace_id === $request->user()->currentWorkspace?->id && $account->platform === Platform::TikTok, HttpResponse::HTTP_FORBIDDEN);

        Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
        SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);

        session()->flash('flash.banner', 'Quá trình đồng bộ dữ liệu đang được chạy ngầm. Vui lòng kiểm tra lại sau ít phút.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function syncAllTikTok(Request $request): RedirectResponse
    {
        $this->authorize('manageAccounts', $request->user()->currentWorkspace);

        $accounts = SocialAccount::query()
            ->where('workspace_id', $request->user()->currentWorkspace?->id)
            ->where('platform', Platform::TikTok)
            ->where('status', SocialAccountStatus::Connected)
            ->get();

        foreach ($accounts as $account) {
            Cache::put("account:syncing:{$account->id}", true, now()->addMinutes(30));
            SyncAccountAnalyticsJob::dispatch($account, $request->user()->id);
        }

        session()->flash('flash.banner', "Đang đồng bộ {$accounts->count()} tài khoản TikTok ngầm. Vui lòng kiểm tra lại sau ít phút.");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
