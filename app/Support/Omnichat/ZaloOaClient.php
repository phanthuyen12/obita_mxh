<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use RuntimeException;

class ZaloOaClient
{
    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $response = Http::asForm()
            ->withHeaders(['secret_key' => (string) config('services.zalo-oa.client_secret')])
            ->timeout(15)
            ->post($this->oauthUrl('/v4/oa/access_token'), [
                'app_id' => config('services.zalo-oa.client_id'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ])->throw()->json();

        $this->throwIfApiError($response, 'Zalo OA token exchange failed');

        return $response;
    }

    /** @return array<string, mixed> */
    public function userProfile(string $accessToken, string $userId): array
    {
        $url = $this->apiUrl('/v3.0/oa/user/detail');
        $query = [
            'data' => json_encode([
                'user_id' => $userId,
            ], JSON_UNESCAPED_UNICODE),
        ];

        Log::info('Zalo OA user profile request', [
            'url' => $url,
            'user_id' => $userId,
            'query' => $query,
            'access_token_present' => $accessToken !== '',
            'access_token_length' => strlen($accessToken),
            'caller' => __METHOD__,
        ]);

        $response = $this->authenticated($accessToken)->get($url, $query);

        Log::info('Zalo OA user profile response', [
            'url' => (string) $response->effectiveUri(),
            'status' => $response->status(),
            'body' => $response->body(),
            'user_id' => $userId,
            'caller' => __METHOD__,
        ]);

        $payload = $response->throw()->json();

        if ((int) data_get($payload, 'error', 0) !== 0) {
            throw new RuntimeException(
                'Zalo OA profile lookup failed: '.
                Str::limit((string) data_get($payload, 'message'), 500)
            );
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function oaProfile(string $accessToken): array
    {
        $response = $this->authenticated($accessToken)
            ->get($this->apiUrl('/v2.0/oa/getoa'))
            ->throw()->json();

        $this->throwIfApiError($response, 'Zalo OA profile lookup failed');

        return $response;
    }

    /** @return array{id: string, payload: array<string, mixed>} */
    public function sendText(SocialAccount $account, string $userId, string $body): array
    {
        return $this->send($account, $userId, ['text' => $body]);
    }

    /** @return array{id: string, payload: array<string, mixed>, attachment: array<string, mixed>} */
    public function sendAttachment(SocialAccount $account, string $userId, UploadedFile $file, ?string $caption): array
    {
        if ($this->isImage($file)) {
            return $this->sendImage($account, $userId, $file, $caption);
        }

        if (! $file->isValid() || $file->getSize() < 1 || $file->getRealPath() === false) {
            throw new RuntimeException('Zalo OA file upload is empty or invalid.');
        }

        $path = 'omnichat/zalo/'.str()->random(40).'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.($file->guessExtension() ?: 'bin');
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $disk->putFileAs('omnichat/zalo', $file, basename($path), 'public');
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Zalo OA file could not be read from server storage.');
        }

        try {
            $upload = $this->authenticatedMultipart($account->access_token)
                ->attach('file', $handle, $file->getClientOriginalName(), ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'])
                ->post($this->apiUrl('/v2.0/oa/upload/file'))
                ->throw()
                ->json();
        } finally {
            fclose($handle);
        }

        $this->throwIfApiError($upload, 'Zalo OA file upload failed');
        $token = data_get($upload, 'data.token', data_get($upload, 'data.attachment_id'));

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Zalo OA file upload returned no file token.');
        }

        $message = [
            'attachment' => [
                'type' => 'file',
                'payload' => ['token' => $token],
            ],
        ];
        if ($caption !== null && $caption !== '') {
            $message['text'] = $caption;
        }

        $sent = $this->send($account, $userId, $message);
        $sent['attachment'] = $this->attachmentMetadata($diskName, $path, $file, $token);

        return $sent;
    }

    /** @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int} */
    public function downloadInboundAttachment(SocialAccount $account, string $url, string $type, ?string $name = null): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Zalo OA inbound attachment URL is invalid.');
        }

        $response = Http::accept('*/*')->withHeaders(['access_token' => (string) $account->access_token])->timeout(20)->retry(2, 250)->get($url)->throw();
        $mimeType = (string) ($response->header('content-type') ?: 'application/octet-stream');
        $extension = Str::lower((string) Str::of($mimeType)->after('/')->before(';'));
        $extension = $extension === 'jpeg' ? 'jpg' : ($extension !== '' ? $extension : 'bin');
        $path = 'omnichat/zalo/inbound/'.str()->random(40).'.'.$extension;
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $disk->put($path, $response->body(), 'public');

        return [
            'id' => hash('sha256', $path),
            'type' => $type === 'media' ? 'image' : $type,
            'url' => $disk->url($path),
            'original_name' => $name ?: basename(parse_url($url, PHP_URL_PATH) ?: $path),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
        ];
    }

    /** @return array{id: string, payload: array<string, mixed>, attachment: array<string, mixed>} */
    public function sendImage(SocialAccount $account, string $userId, UploadedFile $image, ?string $caption): array
    {
        if (! $image->isValid() || $image->getSize() < 1 || $image->getRealPath() === false) {
            throw new RuntimeException('Zalo OA image upload is empty or invalid.');
        }

        $path = 'omnichat/zalo/'.str()->random(40).'.jpg';
        $encodedImage = ImageManager::usingDriver(GdDriver::class)
            ->decodePath($image->getRealPath())
            ->orient()
            ->scaleDown(width: 2048, height: 2048)
            ->encodeUsingFormat(Format::JPEG, quality: 85);
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $disk->put($path, $encodedImage->toString(), 'public');

        $upload = $this->authenticatedMultipart($account->access_token)
            ->attach('file', $encodedImage->toString(), basename($path), ['Content-Type' => 'image/jpeg'])
            ->post($this->apiUrl('/v2.0/oa/upload/image'))
            ->throw()
            ->json();

        $this->throwIfApiError($upload, 'Zalo OA image upload failed');
        $attachmentId = data_get($upload, 'data.attachment_id');
        Log::debug('Zalo OA image upload response', [
            'response' => $upload,
            'attachment_id' => $attachmentId,
            'caller' => __METHOD__,
        ]);
        if (! is_string($attachmentId) || $attachmentId === '') {
            throw new RuntimeException('Zalo OA image upload returned no attachment ID.');
        }

        $message = [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'media',
                    'elements' => [[
                        'media_type' => 'image',
                        'attachment_id' => $attachmentId,
                    ]],
                ],
            ],
        ];

        if ($caption !== null && $caption !== '') {
            $message['text'] = $caption;
        }

        $sent = $this->send($account, $userId, $message);
        $sent['attachment'] = [
            ...$this->attachmentMetadata($diskName, $path, $image, $attachmentId),
        ];

        return $sent;
    }

    /** @return array{id: string, payload: array<string, mixed>} */
    public function sendImageUrl(SocialAccount $account, string $userId, string $url, ?string $caption = null): array
    {
        throw new RuntimeException('Zalo OA image URL sending is not supported; upload the image file first.');
    }

    /** @return array{id: string, payload: array<string, mixed>} */
    public function sendFile(SocialAccount $account, string $userId, string $token): array
    {
        return $this->send($account, $userId, [
            'attachment' => [
                'type' => 'file',
                'payload' => ['token' => $token],
            ],
        ]);
    }

    /** @return array{id: string, payload: array<string, mixed>} */
    public function sendSticker(SocialAccount $account, string $userId, string $attachmentId): array
    {
        return $this->send($account, $userId, [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'media',
                    'elements' => [[
                        'media_type' => 'sticker',
                        'attachment_id' => $attachmentId,
                    ]],
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function react(SocialAccount $account, string $userId, string $messageId, string $icon): array
    {
        $response = $this->authenticated($account->access_token)
            ->post($this->apiUrl('/v2.0/oa/message'), [
                'recipient' => ['user_id' => $userId],
                'sender_action' => [
                    'react_icon' => $icon,
                    'react_message_id' => $messageId,
                ],
            ])
            ->throw()
            ->json();

        if ((int) data_get($response, 'error', 0) !== 0) {
            throw new RuntimeException('Zalo OA reaction failed: '.Str::limit((string) data_get($response, 'message'), 500));
        }

        return $response;
    }

    /** @param array<string, mixed> $message
     * @return array{id: string, payload: array<string, mixed>}
     */
    private function send(SocialAccount $account, string $userId, array $message): array
    {
        $payload = ['recipient' => ['user_id' => $userId], 'message' => $message, 'type' => 'template'];
        $response = $this->authenticated($account->access_token)
            ->post($this->apiUrl('/v3.0/oa/message/cs'), $payload)
            ->throw()->json();

        if ((int) data_get($response, 'error', 0) !== 0) {
            throw new RuntimeException('Zalo OA send failed: '.Str::limit((string) data_get($response, 'message'), 500));
        }

        $messageId = data_get($response, 'data.message_id');

        if (! is_string($messageId) || $messageId === '') {
            throw new RuntimeException('Zalo OA returned no message ID.');
        }

        return ['id' => $messageId, 'payload' => $response];
    }

    private function authenticated(string $accessToken): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders(['access_token' => $accessToken])->timeout(15)->retry(2, 250);
    }

    private function authenticatedMultipart(string $accessToken): PendingRequest
    {
        return Http::acceptJson()->withHeaders(['access_token' => $accessToken])->timeout(15)->retry(2, 250);
    }

    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getMimeType(), 'image/');
    }

    /** @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int} */
    private function attachmentMetadata(string $diskName, string $path, UploadedFile $file, string $id): array
    {
        return [
            'id' => $id,
            'type' => $this->isImage($file) ? 'image' : 'document',
            'url' => Storage::disk($diskName)->url($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('trypost.platforms.zalo-oa.api'), '/').$path;
    }

    private function oauthUrl(string $path): string
    {
        return rtrim((string) config('trypost.platforms.zalo-oa.oauth_api'), '/').$path;
    }

    /** @param array<string, mixed> $response */
    private function throwIfApiError(array $response, string $prefix): void
    {
        if ((int) data_get($response, 'error', 0) !== 0) {
            throw new RuntimeException($prefix.': '.Str::limit((string) data_get($response, 'message', 'Unknown Zalo API error'), 500));
        }
    }
}
