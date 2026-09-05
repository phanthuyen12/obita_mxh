<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\OmnichatChannel;
use App\Models\Workspace;
use App\Support\Omnichat\LazadaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class LazadaController extends SocialController
{
    protected Platform $platform = Platform::Lazada;

    public function connect(Request $request): SymfonyResponse
    {
        $this->ensurePlatformEnabled();
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('connectAccounts', $workspace);
        $state = Str::random(64);
        session(['social_connect_workspace' => $workspace->id, 'lazada_oauth_state' => $state]);

        return Inertia::location(rtrim((string) config('trypost.platforms.lazada.authorize_url'), '/').'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'force_auth' => 'true',
            'redirect_uri' => config('services.lazada.redirect'),
            'client_id' => config('services.lazada.app_key'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request, LazadaClient $client): Response
    {
        $workspace = Workspace::query()->find(session('social_connect_workspace'));
        $expectedState = (string) session('lazada_oauth_state');

        if ($workspace === null || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        if ($expectedState === '' || ! hash_equals($expectedState, $request->string('state')->toString())) {
            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }

        try {
            $tokens = $client->exchangeAuthorizationCode($request->string('code')->toString());
            $accessToken = (string) data_get($tokens, 'access_token');
            $seller = collect(data_get($tokens, 'country_user_info', []))->first();
            $sellerId = (string) data_get($seller, 'seller_id', data_get($tokens, 'account_id'));

            if ($accessToken === '' || $sellerId === '') {
                throw new \RuntimeException('Lazada OAuth response is incomplete.');
            }

            $account = $workspace->socialAccounts()->updateOrCreate(
                ['platform' => Platform::Lazada->value, 'platform_user_id' => $sellerId],
                [
                    'username' => data_get($seller, 'short_code', $sellerId),
                    'display_name' => data_get($tokens, 'account', 'Lazada Shop'),
                    'access_token' => $accessToken,
                    'refresh_token' => data_get($tokens, 'refresh_token'),
                    'token_expires_at' => now()->addSeconds((int) data_get($tokens, 'expires_in', 864000)),
                    'scopes' => ['im'],
                    'status' => Status::Connected,
                    'meta' => ['country' => data_get($seller, 'country', data_get($tokens, 'country')), 'seller' => $seller],
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            OmnichatChannel::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'provider' => ChannelProvider::Lazada, 'external_id' => $sellerId],
                [
                    'social_account_id' => $account->id,
                    'name' => $account->display_name,
                    'access_token' => $accessToken,
                    'refresh_token' => data_get($tokens, 'refresh_token'),
                    'token_expires_at' => $account->token_expires_at,
                    'refresh_token_expires_at' => now()->addSeconds((int) data_get($tokens, 'refresh_expires_in', 4320000)),
                    'capabilities' => ['messages', 'images'],
                    'status' => ChannelStatus::Connected,
                    'connected_at' => now(),
                ],
            );

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (Throwable $exception) {
            Log::error('Lazada OAuth failed', ['message' => $exception->getMessage()]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }
}
