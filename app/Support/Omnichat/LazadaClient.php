<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LazadaClient
{
    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code): array
    {
        return $this->request('/auth/token/create', ['code' => $code], null, true);
    }

    /** @return array<string, mixed> */
    public function sendText(SocialAccount $account, string $sessionId, string $body): array
    {
        return $this->request('/im/message/send', [
            'template_id' => 1,
            'session_id' => $sessionId,
            'txt' => $body,
        ], $account->access_token);
    }

    /** @return array<string, mixed> */
    public function sendImage(SocialAccount $account, string $sessionId, string $imageUrl, int $width, int $height): array
    {
        return $this->request('/im/message/send', [
            'template_id' => 3,
            'session_id' => $sessionId,
            'img_url' => $imageUrl,
            'width' => $width,
            'height' => $height,
        ], $account->access_token);
    }

    /** @param array<string, int|string> $businessParameters
     * @return array<string, mixed>
     */
    private function request(string $path, array $businessParameters, ?string $accessToken, bool $authApi = false): array
    {
        $parameters = array_merge($businessParameters, [
            'app_key' => (string) config('services.lazada.app_key'),
            'timestamp' => now()->getTimestampMs(),
            'sign_method' => 'sha256',
        ]);

        if ($accessToken !== null) {
            $parameters['access_token'] = $accessToken;
        }

        $parameters['sign'] = $this->signature($path, $parameters);
        $baseUrl = $authApi
            ? (string) config('trypost.platforms.lazada.auth_api')
            : (string) config('trypost.platforms.lazada.api');
        $response = Http::asForm()->acceptJson()->timeout(15)->retry(2, 250)
            ->post(rtrim($baseUrl, '/').$path, $parameters)
            ->throw()->json();

        $code = data_get($response, 'code');
        if ($code !== null && (string) $code !== '0') {
            throw new RuntimeException('Lazada API failed: '.(string) data_get($response, 'message', data_get($response, 'detail')));
        }

        return $response;
    }

    /** @param array<string, int|string> $parameters */
    private function signature(string $path, array $parameters): string
    {
        ksort($parameters, SORT_STRING);
        $signingString = $path;
        foreach ($parameters as $key => $value) {
            $signingString .= $key.(string) $value;
        }

        return strtoupper(hash_hmac('sha256', $signingString, (string) config('services.lazada.app_secret')));
    }
}
