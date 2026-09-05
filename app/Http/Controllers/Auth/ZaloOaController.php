<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\OmnichatChannel;
use App\Models\Workspace;
use App\Support\Omnichat\ZaloOaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ZaloOaController extends SocialController
{
    protected Platform $platform = Platform::ZaloOa;

    public function connect(Request $request): SymfonyResponse
    {
        $this->ensurePlatformEnabled();
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('connectAccounts', $workspace);

        $verifier = Str::random(96);
        $state = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        session([
            'social_connect_workspace' => $workspace->id,
            'zalo_oauth_verifier' => $verifier,
            'zalo_oauth_state' => $state,
        ]);

        return Inertia::location(rtrim((string) config('trypost.platforms.zalo-oa.oauth_api'), '/').'/v4/oa/permission?'.http_build_query([
            'app_id' => config('services.zalo-oa.client_id'),
            'redirect_uri' => config('services.zalo-oa.redirect'),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, ZaloOaClient $client): Response
    {
        $workspace = Workspace::query()->find(session('social_connect_workspace'));

        if ($workspace === null || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $expectedState = (string) session('zalo_oauth_state');
        if ($expectedState === '' || ! hash_equals($expectedState, $request->string('state')->toString())) {
            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }

        try {
            if ($request->string('error')->isNotEmpty()) {
                throw new \RuntimeException($request->string('error_description')->toString()
                    ?: $request->string('error')->toString());
            }

            if ($request->string('code')->isEmpty()) {
                throw new \RuntimeException('Zalo OAuth callback did not contain an authorization code.');
            }

            $tokens = $client->exchangeAuthorizationCode(
                $request->string('code')->toString(),
                (string) session('zalo_oauth_verifier'),
            );
            $accessToken = (string) data_get($tokens, 'access_token');
            $profileResponse = $client->oaProfile($accessToken);
            $profile = data_get($profileResponse, 'data', []);
            $oaId = (string) data_get($profile, 'oa_id', data_get($profile, 'id'));

            if ($accessToken === '' || $oaId === '') {
                throw new \RuntimeException('Zalo OA OAuth response is incomplete.');
            }

            $account = $workspace->socialAccounts()->updateOrCreate(
                ['platform' => Platform::ZaloOa->value, 'platform_user_id' => $oaId],
                [
                    'username' => $oaId,
                    'display_name' => (string) data_get($profile, 'name', 'Zalo OA'),
                    'access_token' => $accessToken,
                    'refresh_token' => data_get($tokens, 'refresh_token'),
                    'token_expires_at' => now()->addSeconds((int) data_get($tokens, 'expires_in', 90000)),
                    'scopes' => ['oa.user', 'oa.message'],
                    'status' => Status::Connected,
                    'meta' => ['zalo_avatar_url' => data_get($profile, 'avatar')],
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            OmnichatChannel::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'provider' => ChannelProvider::ZaloOa, 'external_id' => $oaId],
                [
                    'social_account_id' => $account->id,
                    'name' => $account->display_name,
                    'avatar_url' => data_get($profile, 'avatar'),
                    'access_token' => $accessToken,
                    'refresh_token' => data_get($tokens, 'refresh_token'),
                    'token_expires_at' => $account->token_expires_at,
                    'capabilities' => ['messages', 'images'],
                    'status' => ChannelStatus::Connected,
                    'connected_at' => now(),
                ],
            );

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (Throwable $exception) {
            Log::error('Zalo OA OAuth failed', ['message' => $exception->getMessage()]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }
}
