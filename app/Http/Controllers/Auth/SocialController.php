<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\SocialAccount\ToggleSocialAccount;
use App\Enums\Omnichat\ChannelProvider;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Enums\UserWorkspace\Role;
use App\Exceptions\SocialAccount\NetworkAlreadyConnectedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\SocialAccountResource;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use App\Models\WordPressSite;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SocialController extends Controller
{
    protected SocialPlatform $platform;

    protected function ensurePlatformEnabled(): void
    {
        if (isset($this->platform) && ! $this->platform->isEnabled()) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'This platform is currently unavailable.');
        }
    }

    protected function ensureNetworkAvailable(Workspace $workspace, SocialPlatform $platform, string $platformUserId): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        $exists = $workspace->socialAccounts()
            ->whereIn('platform', $platform->networkPlatformValues())
            ->where('platform_user_id', '!=', $platformUserId)
            ->exists();

        if ($exists) {
            throw new NetworkAlreadyConnectedException($platform);
        }
    }

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('connectAccounts', $workspace);

        $canManageAccounts = $request->user()->can('manageAccounts', $workspace);
        $accessibleAccounts = $workspace->socialAccounts()->accessibleBy($request->user());
        $accountTotals = [
            'all' => (clone $accessibleAccounts)->count(),
            'owned' => (clone $accessibleAccounts)->where('connected_by_user_id', $request->user()->id)->count(),
            'shared' => (clone $accessibleAccounts)->where('connected_by_user_id', '!=', $request->user()->id)->count(),
        ];
        $platformCounts = (clone $accessibleAccounts)
            ->selectRaw('platform, COUNT(*) AS aggregate')
            ->groupBy('platform')
            ->pluck('aggregate', 'platform');
        $networkAccountCounts = $platformCounts->reduce(function (array $counts, $count, string $platform): array {
            $network = SocialPlatform::from($platform)->network();
            $counts[$network] = ($counts[$network] ?? 0) + (int) $count;

            return $counts;
        }, []);

        $wpSites = $workspace->wordPressSites()->latest()->get();
        if ($wpSites->isNotEmpty()) {
            $networkAccountCounts['wordpress'] = $wpSites->count();
            $accountTotals['all'] += $wpSites->count();
            $accountTotals['owned'] += $wpSites->count();
        }

        $accountPreviews = $platformCounts->keys()
            ->flatMap(fn (string $platform) => (clone $accessibleAccounts)
                ->where('platform', $platform)
                ->with(['sharedUsers:id', 'workspace'])
                ->orderBy('id')
                ->limit(3)
                ->get())
            ->groupBy(fn (SocialAccount $account): string => $account->platform->network())
            ->flatMap(fn ($accounts) => $accounts->take(3))
            ->values();

        $connectedAccountsList = SocialAccountResource::collection($accountPreviews)->resolve();

        if ($wpSites->isNotEmpty()) {
            $wpPreviews = $wpSites->take(3)->map(fn (WordPressSite $site): array => [
                'id' => $site->id,
                'platform' => 'wordpress',
                'network' => 'wordpress',
                'username' => $site->username,
                'display_name' => $site->name,
                'display_label' => $site->name,
                'handle_label' => $site->url,
                'avatar_url' => '/images/accounts/wordpress.svg',
                'status' => $site->status->value === 'connected' ? 'connected' : 'disconnected',
                'ownership_type' => 'owned',
                'can_disconnect' => $canManageAccounts,
                'can_share' => false,
                'shared_user_ids' => [],
                'shared_user_permissions' => (object) [],
            ])->all();

            $connectedAccountsList = array_merge($connectedAccountsList, $wpPreviews);
        }

        $accountPage = $this->filteredAccounts($request)
            ->with(['sharedUsers:id', 'workspace'])
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate(24, ['*'], 'account_page')
            ->withQueryString()
            ->through(fn ($account) => (new SocialAccountResource($account))->resolve());
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $groupQuery = $workspace->socialAccountGroups()
            ->withCount('socialAccounts')
            ->when(
                trim((string) $request->input('group_search')) !== '',
                fn ($query) => $query->where('name', $likeOperator, '%'.trim((string) $request->input('group_search')).'%'),
            )
            ->orderBy('name');
        $pageGroups = $groupQuery
            ->paginate(9, ['*'], 'group_page')
            ->withQueryString();
        $pageGroups->getCollection()->each(function ($group): void {
            $group->setRelation(
                'socialAccounts',
                $group->socialAccounts()
                    ->orderBy('display_name')
                    ->limit(6)
                    ->get(['social_accounts.id', 'display_name', 'username', 'avatar_url', 'platform']),
            );
        });

        return Inertia::render('accounts/Index', [
            'workspace' => $workspace,
            'platforms' => SocialPlatform::connectableOptions(),
            'connectedAccounts' => $connectedAccountsList,
            'accountPage' => $accountPage,
            'accountTotals' => $accountTotals,
            'networkAccountCounts' => $networkAccountCounts,
            'canManageAccounts' => $canManageAccounts,
            'websiteChatChannels' => OmnichatChannel::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', ChannelProvider::Website)
                ->when(
                    ! $canManageAccounts,
                    fn ($query) => $query->whereHas('sharedUsers', fn ($query) => $query->whereKey($request->user()->id)),
                )
                ->with('sharedUsers:id')
                ->orderBy('name')
                ->get()
                ->map(fn (OmnichatChannel $channel): array => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'status' => $channel->status->value,
                    'authorized_origins' => data_get($channel->settings, 'authorized_origins', []),
                    'public_key' => $channel->access_token,
                    'settings' => $channel->settings ?? [],
                    'can_share' => $canManageAccounts,
                    'shared_user_ids' => $channel->sharedUsers->pluck('id')->values(),
                    'shared_user_permissions' => $channel->sharedUsers->mapWithKeys(fn ($user): array => [
                        $user->id => [
                            'can_view_omnichat' => (bool) $user->pivot->can_view_omnichat,
                            'can_reply_omnichat' => (bool) $user->pivot->can_reply_omnichat,
                            'can_assign_conversations' => (bool) $user->pivot->can_assign_conversations,
                        ],
                    ]),
                ]),
            'wordPressSites' => $workspace->wordPressSites()
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
                ]),
            'pageGroups' => $canManageAccounts
                ? $pageGroups
                : [],
            'groupOptions' => $canManageAccounts
                ? $workspace->socialAccountGroups()->orderBy('name')->get(['id', 'name'])
                : [],
            'accountFilters' => [
                'search' => (string) $request->input('search', ''),
                'platform' => (string) $request->input('platform', ''),
                'group' => (string) $request->input('group', ''),
                'ownership' => (string) $request->input('ownership', 'owned'),
                'group_search' => (string) $request->input('group_search', ''),
            ],
            'members' => $canManageAccounts
                ? $workspace->members()->wherePivot('role', Role::Member->value)->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                : [],
        ]);
    }

    public function browser(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('connectAccounts', $workspace);

        $perPage = min(max($request->integer('per_page', 50), 1), 50);
        $accounts = $workspace->socialAccounts()
            ->accessibleBy($request->user())
            ->with(['sharedUsers:id', 'workspace'])
            ->when(trim((string) $request->input('search')) !== '', function ($query) use ($request): void {
                $search = '%'.Str::lower(trim((string) $request->input('search'))).'%';
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(display_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$search]);
                });
            })
            ->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->string('platform')))
            ->when($request->filled('network'), function ($query) use ($request): void {
                $platforms = collect(SocialPlatform::cases())
                    ->filter(fn (SocialPlatform $platform): bool => $platform->network() === (string) $request->input('network'))
                    ->map(fn (SocialPlatform $platform): string => $platform->value)
                    ->all();
                $query->whereIn('platform', $platforms);
            })
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return SocialAccountResource::collection($accounts);
    }

    private function filteredAccounts(Request $request): Builder|HasMany
    {
        $query = $request->user()->currentWorkspace->socialAccounts()
            ->accessibleBy($request->user());

        if ($search = trim((string) $request->input('search'))) {
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($query) use ($search, $likeOperator): void {
                $query->where('display_name', $likeOperator, "%{$search}%")
                    ->orWhere('username', $likeOperator, "%{$search}%");
            });
        }

        $query->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->string('platform')))
            ->when($request->filled('group'), fn ($query) => $query->whereHas('groups', fn ($query) => $query->whereKey($request->string('group'))));

        return match ($request->input('ownership', 'owned')) {
            'owned' => $query->where('connected_by_user_id', $request->user()->id),
            'shared' => $query->where('connected_by_user_id', '!=', $request->user()->id),
            default => $query,
        };
    }

    public function disconnect(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manage', $account);

        // Drop pending platform rows from drafts/scheduled posts so the account
        // disappears cleanly from their UI. Published/failed rows survive via the
        // FK's nullOnDelete cascade and keep their snapshot fields for history.
        $account->postPlatforms()
            ->where('status', PostPlatformStatus::Pending->value)
            ->delete();

        $account->delete();

        session()->flash('flash.banner', __('accounts.flash.disconnected'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function batchDisconnect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*' => ['required', 'string', 'exists:social_accounts,id'],
        ]);

        $accounts = SocialAccount::query()
            ->whereIn('id', $validated['accounts'])
            ->where('workspace_id', $request->user()->currentWorkspace->id)
            ->get();

        foreach ($accounts as $account) {
            $this->authorize('manage', $account);

            $account->postPlatforms()
                ->where('status', PostPlatformStatus::Pending->value)
                ->delete();

            $account->delete();
        }

        session()->flash('flash.banner', __('accounts.flash.batch_disconnected'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function toggleActive(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->authorize('manage', $account);

        ToggleSocialAccount::execute($account);

        $status = $account->is_active ? 'activated' : 'deactivated';
        session()->flash('flash.banner', __("accounts.flash.{$status}"));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    protected function redirectToProvider(Request $request, string $driver, array $scopes): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('connectAccounts', $workspace);

        session(['social_connect_workspace' => $workspace->id]);

        return Inertia::location(
            Socialite::driver($driver)
                ->scopes($scopes)
                ->redirect()
                ->getTargetUrl()
        );
    }

    protected function handleCallback(
        Request $request,
        SocialPlatform $platform,
        string $driver
    ): Response {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $platform->value);
        }

        try {
            $socialUser = Socialite::driver($driver)->user();

            $this->ensureNetworkAvailable($workspace, $platform, (string) $socialUser->getId());

            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $platform->value,
                    'platform_user_id' => $socialUser->getId(),
                ],
                [
                    'username' => $socialUser->getNickname(),
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $socialUser->approvedScopes ?? null,
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $platform->value);
        } catch (NetworkAlreadyConnectedException) {
            return $this->popupCallback(false, __('accounts.popup_callback.network_taken'), $platform->value);
        } catch (\Exception $e) {
            Log::error('Social OAuth Error', [
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $platform->value);
        }
    }

    protected function forgetSocialConnectSession(): void
    {
        session()->forget('social_connect_workspace');
    }

    /**
     * Render the Inertia page that notifies the opener and closes the connect
     * popup. Used by both the GET OAuth callbacks (a fresh popup page load) and
     * the XHR selection submits (an Inertia visit that swaps to this page).
     *
     * Always pass `onboardingProgress` as inline false so it overrides the shared
     * deferred prop: after select the URL is still the select path, and a deferred
     * reload would re-GET that route with a cleared session.
     */
    protected function popupCallback(bool $success, string $message, ?string $platform = null): Response
    {
        $this->forgetSocialConnectSession();

        return Inertia::render('accounts/PopupCallback', [
            'success' => $success,
            'message' => $message,
            'platform' => $platform,
            'onboardingProgress' => false,
        ]);
    }
}
