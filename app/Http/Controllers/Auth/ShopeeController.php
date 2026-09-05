<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Jobs\Omnichat\SyncShopeeConversations as SyncShopeeConversationsJob;
use App\Models\OmnichatChannel;
use App\Models\Workspace;
use App\Support\Omnichat\ShopeeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ShopeeController extends SocialController
{
    protected Platform $platform = Platform::Shopee;

    public function connect(Request $request, ShopeeClient $client): SymfonyResponse
    {
        $this->ensurePlatformEnabled();
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('connectAccounts', $workspace);
        $state = Str::random(64);
        session(['social_connect_workspace' => $workspace->id, 'shopee_oauth_state' => $state]);
        $oauthContext = [
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->getAuthIdentifier(),
            'state' => $state,
        ];
        Cache::put('shopee_oauth:'.hash('sha256', $state), $oauthContext, now()->addMinutes(10));
        Cache::put('shopee_oauth_user:'.$request->user()->getAuthIdentifier(), $oauthContext, now()->addMinutes(10));

        return Inertia::location($client->authorizationUrl($state));
    }

    public function callback(Request $request, ShopeeClient $client): Response
    {
        $returnedState = (string) $request->route('state');
        $expectedState = (string) session('shopee_oauth_state');
        $oauthContext = $returnedState !== ''
            ? Cache::pull('shopee_oauth:'.hash('sha256', $returnedState))
            : (Cache::pull('shopee_oauth_user:'.$request->user()->getAuthIdentifier())
                ?? ($expectedState !== '' ? Cache::pull('shopee_oauth:'.hash('sha256', $expectedState)) : null));
        $hasValidContext = is_array($oauthContext)
            && hash_equals((string) data_get($oauthContext, 'user_id'), (string) $request->user()->getAuthIdentifier());
        $contextState = (string) data_get($oauthContext, 'state');
        $hasValidSession = $expectedState !== ''
            && ($returnedState === '' || hash_equals($expectedState, $returnedState))
            && ($contextState === '' || hash_equals($expectedState, $contextState));

        if (! $hasValidContext && ! $hasValidSession) {
            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }

        $workspaceId = data_get($oauthContext, 'workspace_id', session('social_connect_workspace'));
        $workspace = Workspace::query()->find($workspaceId);
        if ($workspace === null || ! $request->user()->can('connectAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        try {
            $shopId = $request->string('shop_id')->toString();
            $tokens = $client->obtainAccessToken($request->string('code')->toString(), $shopId);
            $account = $workspace->socialAccounts()->updateOrCreate(
                ['platform' => Platform::Shopee->value, 'platform_user_id' => $shopId],
                ['username' => $shopId, 'display_name' => data_get($tokens, 'shop_name', 'Shopee'),
                    'access_token' => $tokens['access_token'], 'refresh_token' => data_get($tokens, 'refresh_token'),
                    'token_expires_at' => now()->addSeconds((int) data_get($tokens, 'expire_in', 14400)),
                    'scopes' => ['sellerchat'], 'status' => Status::Connected,
                    'meta' => ['region' => data_get($tokens, 'region', 'VN')], 'error_message' => null, 'disconnected_at' => null],
            );
            $shopInfo = $client->shopInfo($account);
            $shopData = data_get($shopInfo, 'response', []);
            $account->update([
                'display_name' => data_get($shopData, 'shop_name', $account->display_name),
                'avatar_url' => data_get($shopData, 'shop_logo'),
            ]);
            OmnichatChannel::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'provider' => ChannelProvider::Shopee, 'external_id' => $shopId],
                ['social_account_id' => $account->id, 'name' => $account->display_name ?? 'Shopee',
                    'avatar_url' => data_get($shopData, 'shop_logo'), 'access_token' => $account->access_token,
                    'refresh_token' => $account->refresh_token, 'token_expires_at' => $account->token_expires_at,
                    'capabilities' => ['messages'], 'status' => ChannelStatus::Connected, 'connected_at' => now()],
            );
            SyncShopeeConversationsJob::dispatch($account->id);

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (Throwable $exception) {
            Log::error('Shopee OAuth failed', ['message' => $exception->getMessage()]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }
}
