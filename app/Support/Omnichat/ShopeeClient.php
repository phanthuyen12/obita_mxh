<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ShopeeClient
{
    private const AUTH_PATH = '/api/v2/shop/auth_partner';

    public function authorizationUrl(string $state): string
    {
        $timestamp = now()->timestamp;
        $parameters = [
            'partner_id' => $this->partnerId(),
            'redirect' => (string) config('services.shopee.redirect'),
            'timestamp' => $timestamp,
            'sign' => $this->signature(self::AUTH_PATH, $timestamp),
        ];

        return rtrim((string) config('trypost.platforms.shopee.auth_api'), '/').self::AUTH_PATH.'?'.http_build_query($parameters);
    }

    /** @return array<string, mixed> */
    public function obtainAccessToken(string $code, string $shopId): array
    {
        return $this->authRequest('/api/v2/auth/token/get', ['code' => $code, 'shop_id' => (int) $shopId]);
    }

    /** @return array<string, mixed> */
    public function renewAccessToken(string $refreshToken, string $shopId): array
    {
        return $this->authRequest('/api/v2/auth/access_token/get', ['refresh_token' => $refreshToken, 'shop_id' => (int) $shopId]);
    }

    /** @return array<string, mixed> */
    public function shopInfo(SocialAccount $account): array
    {
        return $this->request($account, '/api/v2/shop/get_shop_info');
    }

    /** @return array<string, mixed> */
    public function conversations(SocialAccount $account, ?string $nextTimestampNano = null): array
    {
        return $this->request($account, '/api/v2/sellerchat/get_conversation_list', array_filter([
            'direction' => 'older', 'type' => 'all', 'next_timestamp_nano' => $nextTimestampNano,
            'page_size' => 60, 'business_type' => 0,
        ], fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    public function conversation(SocialAccount $account, string $conversationId, int $businessType = 0): array
    {
        return $this->request($account, '/api/v2/sellerchat/get_one_conversation', [
            'conversation_id' => $conversationId, 'business_type' => $businessType,
        ]);
    }

    /** @return array<string, mixed> */
    public function messages(SocialAccount $account, string $conversationId, ?string $offset = null, int $businessType = 0): array
    {
        return $this->request($account, '/api/v2/sellerchat/get_message', array_filter([
            'conversation_id' => $conversationId, 'offset' => $offset, 'page_size' => 60,
            'business_type' => $businessType,
        ], fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    public function sendText(SocialAccount $account, string $toId, string $body, ?string $conversationId = null, int $businessType = 0): array
    {
        return $this->request($account, '/api/v2/sellerchat/send_message', array_filter([
            'to_id' => (int) $toId, 'message_type' => 'text', 'content' => ['text' => $body],
            'conversation_id' => $businessType === 0 ? null : $conversationId, 'business_type' => $businessType,
        ], fn (mixed $value): bool => $value !== null), 'post');
    }

    public function signature(string $path, int $timestamp, ?string $accessToken = null, ?string $shopId = null): string
    {
        $payload = $this->partnerId().$path.$timestamp;
        if ($accessToken !== null && $shopId !== null) {
            $payload .= trim($accessToken).trim($shopId);
        }

        return hash_hmac('sha256', $payload, $this->partnerKey());
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function authRequest(string $path, array $parameters): array
    {
        $timestamp = now()->timestamp;
        $response = $this->http()->post(rtrim((string) config('trypost.platforms.shopee.api'), '/').$path.'?'.http_build_query([
            'partner_id' => $this->partnerId(), 'timestamp' => $timestamp,
            'sign' => $this->signature($path, $timestamp),
        ]), $parameters + ['partner_id' => (int) $this->partnerId()]);

        $payload = $this->payload($response->throw()->json(), $path);
        $tokens = data_get($payload, 'response', $payload);

        if (! is_array($tokens) || blank($tokens['access_token'] ?? null)) {
            throw new RuntimeException("Shopee API {$path} returned an invalid token response.");
        }

        return $tokens;
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function request(SocialAccount $account, string $path, array $parameters = [], string $method = 'get'): array
    {
        $accessToken = $this->validAccessToken($account);
        $shopId = $account->platform_user_id;
        $timestamp = now()->timestamp;
        $common = [
            'partner_id' => $this->partnerId(), 'timestamp' => $timestamp,
            'sign' => $this->signature($path, $timestamp, $accessToken, $shopId),
            'shop_id' => $shopId, 'access_token' => $accessToken,
        ];
        $url = rtrim((string) config('trypost.platforms.shopee.api'), '/').$path;
        $response = $method === 'post'
            ? $this->http()->post($url.'?'.http_build_query($common), $parameters)
            : $this->http()->get($url, $common + $parameters);

        return $this->payload($response->throw()->json(), $path);
    }

    private function validAccessToken(SocialAccount $account): string
    {
        if ($account->token_expires_at === null || $account->token_expires_at->isAfter(now()->addMinutes(30))) {
            return (string) $account->access_token;
        }
        if (! is_string($account->refresh_token) || $account->refresh_token === '') {
            throw new RuntimeException('Shopee refresh token is missing; reauthorization is required.');
        }

        $tokens = $this->renewAccessToken($account->refresh_token, $account->platform_user_id);
        $account->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => data_get($tokens, 'refresh_token', $account->refresh_token),
            'token_expires_at' => now()->addSeconds((int) data_get($tokens, 'expire_in', 14400)),
        ]);

        return (string) $account->fresh()->access_token;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payload(array $payload, string $path): array
    {
        if (filled($payload['error'] ?? null)) {
            throw new RuntimeException("Shopee API {$path} returned {$payload['error']}: ".Str::limit((string) ($payload['message'] ?? ''), 500));
        }

        return $payload;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->connectTimeout(5)->timeout(15)->retry(2, 250);
    }

    private function partnerId(): string
    {
        return trim((string) config('services.shopee.partner_id')) ?: throw new RuntimeException('SHOPEE_PARTNER_ID is not configured.');
    }

    private function partnerKey(): string
    {
        return trim((string) config('services.shopee.partner_key')) ?: throw new RuntimeException('SHOPEE_PARTNER_KEY is not configured.');
    }
}
