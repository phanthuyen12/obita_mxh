<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StoreWordPressSiteRequest;
use App\Http\Requests\App\UpdateWordPressSiteRequest;
use App\Models\WordPressSite;
use App\Services\WordPress\WordPressApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WordPressSiteController extends Controller
{
    public function __construct(private readonly WordPressApiClient $wpClient) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_if(! $workspace, 403, 'Bạn chưa chọn workspace');

        $sites = $workspace->wordPressSites()
            ->latest()
            ->get()
            ->map(fn (WordPressSite $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'username' => $site->username,
                'status' => $site->status->value,
                'error_message' => $site->error_message,
                'wp_user_name' => $site->wp_user_name,
                'categories_count' => count($site->categories_cache ?? []),
                'tags_count' => count($site->tags_cache ?? []),
                'last_synced_at' => $site->last_synced_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('wordpress/sites/Index', [
            'sites' => $sites,
        ]);
    }

    public function store(StoreWordPressSiteRequest $request): RedirectResponse
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_if(! $workspace, 403, 'Bạn chưa chọn workspace');

        $validated = $request->validated();

        $baseUrl = $this->wpClient->formatBaseUrl($validated['url']);

        // 1. Kiểm tra kết nối thử trước
        $verify = $this->wpClient->verifyConnection(
            $baseUrl,
            $validated['username'],
            $validated['application_password'],
        );

        if (! $verify['success']) {
            return back()->withErrors(['connection' => $verify['error'] ?? 'Không thể kết nối đến website WordPress']);
        }

        // 2. Lưu vào CSDL WordPressSite
        $site = $workspace->wordPressSites()->create([
            'name' => $validated['name'],
            'url' => $baseUrl,
            'username' => trim($validated['username']),
            'application_password' => trim($validated['application_password']),
            'status' => 'connected',
            'wp_user_id' => $verify['user_id'] ?? null,
            'wp_user_name' => $verify['user_name'] ?? null,
        ]);

        // 3. Đồng bộ danh mục & thẻ
        $this->wpClient->syncTaxonomies($site);

        // 4. Đồng bộ tạo SocialAccount tương ứng để dùng được đầy đủ cho Post Editor, Sharing và Workflow
        $workspace->socialAccounts()->updateOrCreate(
            [
                'platform' => SocialPlatform::WordPress,
                'platform_user_id' => $baseUrl,
            ],
            [
                'connected_by_user_id' => $request->user()->id,
                'username' => trim($validated['username']),
                'display_name' => $validated['name'],
                'avatar_url' => '/images/accounts/wordpress.svg',
                'access_token' => trim($validated['application_password']),
                'status' => Status::Connected,
                'is_active' => true,
                'scopes' => ['publish'],
                'meta' => [
                    'site_id' => $site->id,
                    'url' => $baseUrl,
                    'username' => trim($validated['username']),
                    'categories' => $site->categories_cache,
                    'tags' => $site->tags_cache,
                ],
            ],
        );

        session()->flash('flash.banner', "Đã kết nối thành công website {$site->name}!");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function update(UpdateWordPressSiteRequest $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($site->workspace_id === $request->user()?->currentWorkspace?->id, 403);

        $validated = $request->validated();
        $oldUrl = $site->url;
        $baseUrl = $this->wpClient->formatBaseUrl($validated['url']);
        $username = trim($validated['username']);
        $applicationPassword = trim((string) ($validated['application_password'] ?: $site->application_password));

        $verify = $this->wpClient->verifyConnection($baseUrl, $username, $applicationPassword);

        if (! $verify['success']) {
            return back()->withErrors(['connection' => $verify['error'] ?? 'Không thể kết nối đến website WordPress']);
        }

        $site->update([
            'name' => $validated['name'],
            'url' => $baseUrl,
            'username' => $username,
            'application_password' => $applicationPassword,
            'status' => 'connected',
            'error_message' => null,
            'wp_user_id' => $verify['user_id'] ?? null,
            'wp_user_name' => $verify['user_name'] ?? null,
        ]);

        $this->wpClient->syncTaxonomies($site);

        $socialAccount = $site->workspace->socialAccounts()->updateOrCreate(
            [
                'platform' => SocialPlatform::WordPress,
                'platform_user_id' => $oldUrl,
            ],
            [
                'connected_by_user_id' => $request->user()->id,
                'username' => $username,
                'display_name' => $validated['name'],
                'avatar_url' => '/images/accounts/wordpress.svg',
                'access_token' => $applicationPassword,
                'status' => Status::Connected,
                'is_active' => true,
                'scopes' => ['publish'],
                'meta' => [
                    'site_id' => $site->id,
                    'url' => $baseUrl,
                    'username' => $username,
                    'categories' => $site->categories_cache,
                    'tags' => $site->tags_cache,
                ],
            ],
        );
        $socialAccount->update(['platform_user_id' => $baseUrl]);

        session()->flash('flash.banner', "Đã cập nhật cấu hình website {$site->name}!");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function destroy(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($site->workspace_id === $request->user()?->currentWorkspace?->id, 403);

        $workspace = $request->user()->currentWorkspace;

        // Xóa SocialAccount tương ứng
        $workspace->socialAccounts()
            ->where('platform', SocialPlatform::WordPress)
            ->where('platform_user_id', $site->url)
            ->delete();

        $site->delete();

        session()->flash('flash.banner', 'Đã xóa website WordPress.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function testConnection(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($site->workspace_id === $request->user()?->currentWorkspace?->id, 403);

        $verify = $this->wpClient->verifyConnection($site->url, $site->username, $site->application_password);

        if ($verify['success']) {
            $site->update([
                'status' => 'connected',
                'error_message' => null,
                'wp_user_id' => $verify['user_id'] ?? $site->wp_user_id,
                'wp_user_name' => $verify['user_name'] ?? $site->wp_user_name,
            ]);
            $this->wpClient->syncTaxonomies($site);

            $site->workspace->socialAccounts()
                ->where('platform', SocialPlatform::WordPress)
                ->where('platform_user_id', $site->url)
                ->update([
                    'status' => Status::Connected,
                    'error_message' => null,
                ]);

            session()->flash('flash.banner', 'Kết nối hoạt động tốt!');
            session()->flash('flash.bannerStyle', 'success');
        } else {
            $site->update([
                'status' => 'error',
                'error_message' => $verify['error'],
            ]);

            $site->workspace->socialAccounts()
                ->where('platform', SocialPlatform::WordPress)
                ->where('platform_user_id', $site->url)
                ->update([
                    'status' => Status::Disconnected,
                    'error_message' => $verify['error'],
                ]);

            session()->flash('flash.banner', 'Kết nối thất bại: '.$verify['error']);
            session()->flash('flash.bannerStyle', 'danger');
        }

        return back();
    }

    public function sync(Request $request, WordPressSite $site): RedirectResponse|JsonResponse
    {
        abort_unless($site->workspace_id === $request->user()?->currentWorkspace?->id, 403);

        $synced = $this->wpClient->syncTaxonomies($site);

        if ($synced) {
            $site->workspace->socialAccounts()
                ->where('platform', SocialPlatform::WordPress)
                ->where('platform_user_id', $site->url)
                ->update([
                    'meta' => [
                        'site_id' => $site->id,
                        'url' => $site->url,
                        'username' => $site->username,
                        'categories' => $site->categories_cache,
                        'tags' => $site->tags_cache,
                    ],
                ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'categories' => $site->categories_cache,
                    'tags' => $site->tags_cache,
                ]);
            }

            session()->flash('flash.banner', 'Đã đồng bộ danh mục & thẻ thành công!');
            session()->flash('flash.bannerStyle', 'success');
        } else {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $site->error_message ?? 'Không thể đồng bộ danh mục từ WordPress',
                ], 422);
            }

            session()->flash('flash.banner', 'Đồng bộ thất bại: '.$site->error_message);
            session()->flash('flash.bannerStyle', 'danger');
        }

        return back();
    }
}
