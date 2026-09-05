<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Exceptions\SocialAccount\NetworkAlreadyConnectedException;
use App\Jobs\SubscribeFacebookPageToWebhooks;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\Meta\GraphPaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class FacebookController extends SocialController
{
    protected string $driver = 'facebook';

    protected SocialPlatform $platform = SocialPlatform::Facebook;

    protected array $scopes = [
        'public_profile',
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'pages_messaging',
        'pages_manage_metadata',
        'read_insights',
    ];

    public function connect(Request $request): Response
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        $this->authorize('connectAccounts', $workspace);

        session([
            'social_connect_workspace' => $workspace->id,
            'social_reconnect_id' => null,
        ]);

        $oauthUrl = Socialite::driver($this->driver)
            ->usingGraphVersion($this->graphVersion())
            ->setScopes($this->scopes)
            ->redirect()
            ->getTargetUrl();

        $uri = Uri::of($oauthUrl)->withoutQuery('scope');
        $query = array_merge($uri->query()->all(), [
            'config_id' => (string) config('services.facebook.login_config_id'),
            'override_default_response_type' => '1',
        ]);

        return Inertia::location((string) $uri->withQuery($query));
    }

    public function callback(Request $request): InertiaResponse|RedirectResponse
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $this->platform->value);
        }

        try {
            $socialUser = Socialite::driver($this->driver)->usingGraphVersion($this->graphVersion())->user();

            // Trigger public_profile and pages_show_list API calls
            // These calls are needed for Meta app review permission verification
            Http::get(config('trypost.platforms.facebook.graph_api').'/me', [
                'fields' => 'id,name',
                'access_token' => $socialUser->token,
            ]);

            // Fetch pages the user manages
            $pages = $this->fetchPages($socialUser->token);

            if (empty($pages)) {
                return $this->popupCallback(false, __('accounts.popup_callback.no_facebook_pages'), $this->platform->value);
            }

            if (count($pages) === 1) {
                $this->connectPage(
                    $workspace,
                    $pages[0],
                    (string) $socialUser->getId(),
                    $socialUser->token,
                );

                session()->forget(['facebook_oauth', 'social_reconnect_id']);

                return $this->popupCallback(
                    true,
                    __('accounts.popup_callback.connected'),
                    $this->platform->value,
                );
            }

            session([
                'facebook_oauth' => [
                    'user_id' => $socialUser->getId(),
                    'user_token' => $socialUser->token,
                    'pages' => $pages,
                    'reconnect_id' => session('social_reconnect_id'),
                ],
            ]);

            return redirect()->route('app.social.facebook.select-page');
        } catch (NetworkAlreadyConnectedException) {
            return $this->popupCallback(false, __('accounts.popup_callback.network_taken'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Facebook OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }

    public function selectPage(Request $request): InertiaResponse
    {
        $oauthData = session('facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $this->platform->value);
        }

        $pages = collect(data_get($oauthData, 'pages'))
            ->map(fn ($page) => Arr::except($page, ['access_token']))
            ->toArray();

        return Inertia::render('accounts/FacebookPageSelect', [
            'workspace' => $workspace,
            'pages' => $pages,
        ]);
    }

    public function select(Request $request): InertiaResponse
    {
        $request->validate([
            'page_id' => 'required|string',
        ]);

        $oauthData = session('facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $this->platform->value);
        }

        try {
            $selectedPage = collect(data_get($oauthData, 'pages'))->firstWhere('id', $request->page_id);

            if (! $selectedPage) {
                return $this->popupCallback(false, __('accounts.popup_callback.page_not_found'), $this->platform->value);
            }

            $avatarPath = uploadFromUrl(data_get($selectedPage, 'picture'));
            $reconnectId = data_get($oauthData, 'reconnect_id');

            if ($reconnectId) {
                // Reconnect existing account
                $existingAccount = $workspace->socialAccounts()->find($reconnectId);

                if ($existingAccount) {
                    $existingAccount->update([
                        'platform_user_id' => data_get($selectedPage, 'id'),
                        'username' => data_get($selectedPage, 'username') ?? null,
                        'display_name' => data_get($selectedPage, 'name'),
                        'avatar_url' => $avatarPath,
                        'access_token' => data_get($selectedPage, 'access_token'),
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'scopes' => $this->scopes,
                        'meta' => [
                            'page_id' => data_get($selectedPage, 'id'),
                            'user_id' => data_get($oauthData, 'user_id'),
                            'user_token' => data_get($oauthData, 'user_token'),
                        ],
                    ]);
                    $existingAccount->markAsConnected();
                    $this->subscribePageToWebhooks($existingAccount);

                    session()->forget(['facebook_oauth', 'social_reconnect_id']);

                    return $this->popupCallback(true, __('accounts.popup_callback.facebook_setup_pending'), $this->platform->value);
                }
            }

            $this->ensureNetworkAvailable($workspace, $this->platform, (string) data_get($selectedPage, 'id'));

            $account = $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $this->platform->value,
                    'platform_user_id' => data_get($selectedPage, 'id'),
                ],
                [
                    'username' => data_get($selectedPage, 'username') ?? null,
                    'display_name' => data_get($selectedPage, 'name'),
                    'avatar_url' => $avatarPath,
                    'access_token' => data_get($selectedPage, 'access_token'),
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'scopes' => $this->scopes,
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                    'meta' => [
                        'page_id' => data_get($selectedPage, 'id'),
                        'user_id' => data_get($oauthData, 'user_id'),
                        'user_token' => data_get($oauthData, 'user_token'),
                    ],
                ],
            );
            $this->subscribePageToWebhooks($account);

            session()->forget(['facebook_oauth', 'social_reconnect_id']);

            return $this->popupCallback(true, __('accounts.popup_callback.facebook_setup_pending'), $this->platform->value);
        } catch (NetworkAlreadyConnectedException) {
            return $this->popupCallback(false, __('accounts.popup_callback.network_taken'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Facebook page selection error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting_page'), $this->platform->value);
        }
    }

    private function fetchPages(string $userToken): array
    {
        $pages = GraphPaginator::all(
            config('trypost.platforms.facebook.graph_api').'/me/accounts',
            [
                'access_token' => $userToken,
                'fields' => 'id,name,username,picture{url},access_token',
                'limit' => 100,
            ],
        );

        return collect($pages)->map(fn (array $page) => [
            'id' => data_get($page, 'id'),
            'name' => data_get($page, 'name'),
            'username' => data_get($page, 'username'),
            'picture' => data_get($page, 'picture.data.url'),
            'access_token' => data_get($page, 'access_token'),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function connectPage(
        Workspace $workspace,
        array $page,
        string $userId,
        string $userToken,
    ): void {
        $this->ensureNetworkAvailable($workspace, $this->platform, (string) data_get($page, 'id'));

        $account = $workspace->socialAccounts()->updateOrCreate(
            [
                'platform' => $this->platform->value,
                'platform_user_id' => data_get($page, 'id'),
            ],
            [
                'username' => data_get($page, 'username'),
                'display_name' => data_get($page, 'name'),
                'avatar_url' => uploadFromUrl(data_get($page, 'picture')),
                'access_token' => data_get($page, 'access_token'),
                'refresh_token' => null,
                'token_expires_at' => null,
                'scopes' => $this->scopes,
                'status' => Status::Connected,
                'error_message' => null,
                'disconnected_at' => null,
                'meta' => [
                    'page_id' => data_get($page, 'id'),
                    'user_id' => $userId,
                    'user_token' => $userToken,
                ],
            ],
        );

        $this->subscribePageToWebhooks($account);
    }

    private function subscribePageToWebhooks(SocialAccount $account): void
    {
        SubscribeFacebookPageToWebhooks::dispatch((string) $account->id)->afterCommit();
    }

    private function graphVersion(): string
    {
        return Uri::of(config('trypost.platforms.facebook.graph_api'))->path();
    }
}
