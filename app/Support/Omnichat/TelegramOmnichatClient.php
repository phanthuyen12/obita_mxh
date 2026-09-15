<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\OmnichatChannel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TelegramOmnichatClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('trypost.platforms.telegram.api', 'https://api.telegram.org'), '/');
    }

    /**
     * Verify a Bot token and return bot information.
     *
     * @return array{id: int, is_bot: bool, first_name: string, username: string|null}
     */
    public function verifyToken(string $token): array
    {
        $response = Http::timeout(10)
            ->get("{$this->baseUrl}/bot{$token}/getMe");

        if (! $response->successful() || ! (bool) data_get($response->json(), 'ok')) {
            $description = (string) data_get($response->json(), 'description', 'Invalid Telegram Bot Token');
            throw new RuntimeException("Telegram API Error: {$description}");
        }

        /** @var array{id: int, is_bot: bool, first_name: string, username: string|null} $result */
        $result = data_get($response->json(), 'result');

        return $result;
    }

    public function buildWebhookUrl(OmnichatChannel $channel): string
    {
        $base = rtrim((string) (config('app.webhook_url') ?: config('app.url')), '/');

        return "{$base}/webhooks/telegram/{$channel->id}";
    }

    /**
     * Get webhook status information from Telegram.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(OmnichatChannel $channel): array
    {
        $token = (string) $channel->access_token;

        $response = Http::timeout(10)->get("{$this->baseUrl}/bot{$token}/getWebhookInfo");

        return $response->json() ?? [];
    }

    /**
     * Register webhook for the Telegram Bot channel.
     */
    public function setWebhook(OmnichatChannel $channel, ?string $webhookUrl = null): bool
    {
        $token = (string) $channel->access_token;
        $secret = (string) $channel->webhook_secret;
        $url = $webhookUrl ?? $this->buildWebhookUrl($channel);

        $response = Http::timeout(10)->post("{$this->baseUrl}/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
            'drop_pending_updates' => false,
        ]);

        return $response->successful() && (bool) data_get($response->json(), 'ok');
    }

    /**
     * Delete webhook for the Telegram Bot channel.
     */
    public function deleteWebhook(OmnichatChannel $channel): bool
    {
        $token = (string) $channel->access_token;

        $response = Http::timeout(10)->post("{$this->baseUrl}/bot{$token}/deleteWebhook", [
            'drop_pending_updates' => false,
        ]);

        return $response->successful() && (bool) data_get($response->json(), 'ok');
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @return array{id: string, payload: array<string, mixed>}
     */
    public function sendMessage(
        OmnichatChannel $channel,
        string|int $chatId,
        string $text,
        ?array $replyMarkup = null,
    ): array {
        $token = (string) $channel->access_token;

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $response = Http::timeout(15)->post("{$this->baseUrl}/bot{$token}/sendMessage", $payload);

        if (! $response->successful() || ! (bool) data_get($response->json(), 'ok')) {
            $desc = (string) data_get($response->json(), 'description', 'Failed to send Telegram message');
            Log::error('[TelegramOmnichat] sendMessage failed', ['response' => $response->json(), 'chat_id' => $chatId]);
            throw new RuntimeException("Telegram sendMessage failed: {$desc}");
        }

        $messageId = (string) data_get($response->json(), 'result.message_id');

        return [
            'id' => $messageId,
            'payload' => $response->json() ?? [],
        ];
    }

    /**
     * Send a photo attachment to a Telegram chat.
     *
     * @return array{id: string, payload: array<string, mixed>, attachment: array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}}
     */
    public function sendPhoto(
        OmnichatChannel $channel,
        string|int $chatId,
        UploadedFile $file,
        ?string $caption = null,
    ): array {
        if (! $file->isValid() || $file->getSize() < 1) {
            throw new RuntimeException('Telegram attachment file is empty or invalid.');
        }

        $token = (string) $channel->access_token;
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $extension = $file->guessExtension() ?: 'jpg';
        $storedPath = $file->storeAs('omnichat/telegram/outbound', Str::random(40).'.'.$extension, [
            'disk' => $diskName,
            'visibility' => 'public',
        ]);
        $publicUrl = $disk->url($storedPath);

        $attachment = [
            'id' => hash('sha256', $storedPath),
            'type' => 'image',
            'url' => $publicUrl,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];

        // Send via multipart upload
        $request = Http::timeout(30)->attach(
            'photo',
            fopen($file->getRealPath(), 'r'),
            $file->getClientOriginalName(),
        );

        $params = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $params['caption'] = $caption;
        }

        $response = $request->post("{$this->baseUrl}/bot{$token}/sendPhoto", $params);

        if (! $response->successful() || ! (bool) data_get($response->json(), 'ok')) {
            $desc = (string) data_get($response->json(), 'description', 'Failed to send Telegram photo');
            Log::error('[TelegramOmnichat] sendPhoto failed', ['response' => $response->json()]);
            throw new RuntimeException("Telegram sendPhoto failed: {$desc}");
        }

        $messageId = (string) data_get($response->json(), 'result.message_id');

        return [
            'id' => $messageId,
            'payload' => $response->json() ?? [],
            'attachment' => $attachment,
        ];
    }

    /**
     * Download an inbound file/media from Telegram and store it on system disk.
     *
     * @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}|null
     */
    public function downloadInboundFile(
        OmnichatChannel $channel,
        string $fileId,
        string $type = 'document',
        ?string $fileName = null,
    ): ?array {
        $token = (string) $channel->access_token;

        try {
            $fileResponse = Http::timeout(10)->get("{$this->baseUrl}/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);

            if (! $fileResponse->successful() || ! (bool) data_get($fileResponse->json(), 'ok')) {
                return null;
            }

            $filePath = data_get($fileResponse->json(), 'result.file_path');
            if (! is_string($filePath) || $filePath === '') {
                return null;
            }

            $downloadUrl = "{$this->baseUrl}/file/bot{$token}/{$filePath}";
            $downloadResponse = Http::timeout(30)->get($downloadUrl);

            if (! $downloadResponse->successful()) {
                return null;
            }

            $diskName = config('filesystems.default');
            $disk = Storage::disk($diskName);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'bin';
            $localName = Str::random(40).'.'.$extension;
            $savePath = "omnichat/telegram/inbound/{$localName}";

            $disk->put($savePath, $downloadResponse->body(), 'public');
            $publicUrl = $disk->url($savePath);

            $mimeType = (string) ($downloadResponse->header('content-type') ?: 'application/octet-stream');
            $fileSize = strlen($downloadResponse->body());

            return [
                'id' => hash('sha256', $savePath),
                'type' => $type,
                'url' => $publicUrl,
                'original_name' => $fileName ?: basename($filePath),
                'mime_type' => $mimeType,
                'size' => $fileSize,
            ];
        } catch (Throwable $e) {
            Log::warning('[TelegramOmnichat] downloadInboundFile failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch user profile avatar and save to public storage disk.
     */
    public function fetchUserProfilePhoto(OmnichatChannel $channel, string|int $userId): ?string
    {
        $token = (string) $channel->access_token;

        try {
            $res = Http::timeout(10)->get("{$this->baseUrl}/bot{$token}/getUserProfilePhotos", [
                'user_id' => $userId,
                'limit' => 1,
            ]);

            if (! $res->successful() || ! (bool) data_get($res->json(), 'ok')) {
                return null;
            }

            $photos = data_get($res->json(), 'result.photos.0');
            if (! is_array($photos) || empty($photos)) {
                return null;
            }

            // Get biggest size (last item in array)
            $biggestPhoto = end($photos);
            $fileId = data_get($biggestPhoto, 'file_id');

            if (! is_string($fileId)) {
                return null;
            }

            $download = $this->downloadInboundFile($channel, $fileId, 'image', "avatar_{$userId}.jpg");

            return $download['url'] ?? null;
        } catch (Throwable $e) {
            Log::warning('[TelegramOmnichat] fetchUserProfilePhoto failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Configure bot menu commands in Telegram.
     *
     * @param  list<array{command: string, description: string}>  $commands
     */
    public function setMyCommands(OmnichatChannel $channel, array $commands): bool
    {
        $token = (string) $channel->access_token;

        $response = Http::timeout(10)->post("{$this->baseUrl}/bot{$token}/setMyCommands", [
            'commands' => $commands,
        ]);

        return $response->successful() && (bool) data_get($response->json(), 'ok');
    }
}
