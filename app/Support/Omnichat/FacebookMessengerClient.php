<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookMessengerClient
{
    /** @return array{id: string, payload: array<string, mixed>} */
    public function sendText(SocialAccount $account, string $recipientId, string $body): array
    {
        return $this->send($account, $recipientId, ['text' => $body]);
    }

    /** @return array{id: string, payload: array<string, mixed>, attachment: array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}} */
    public function sendAttachment(SocialAccount $account, string $recipientId, UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() < 1) {
            throw new RuntimeException('Facebook Messenger attachment is empty or invalid.');
        }

        $path = 'omnichat/facebook/'.Str::random(40).'.'.($file->guessExtension() ?: 'bin');
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $disk->putFileAs('omnichat/facebook', $file, basename($path), 'public');

        $attachment = $this->attachmentMetadata($diskName, $path, $file, hash('sha256', $path));
        $attachmentId = $this->uploadAttachment($account, $file, $attachment['type']);
        $result = $this->send($account, $recipientId, [
            'attachment' => [
                'type' => $attachment['type'],
                'payload' => [
                    'attachment_id' => $attachmentId,
                ],
            ],
        ]);

        return [...$result, 'attachment' => $attachment];
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}|null
     */
    public function downloadInboundAttachment(array $attachment): ?array
    {
        $url = data_get($attachment, 'payload.url');
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $type = $this->attachmentType((string) data_get($attachment, 'type'));

        try {
            $response = Http::accept('*/*')->timeout(20)->retry(2, 250)->get($url)->throw();
        } catch (\Throwable) {
            return $this->remoteAttachmentMetadata($attachment, $url, $type);
        }

        $mimeType = (string) ($response->header('content-type') ?: 'application/octet-stream');
        $extension = Str::lower((string) Str::of($mimeType)->after('/')->before(';'));
        $path = 'omnichat/facebook/inbound/'.Str::random(40).'.'.($extension !== '' ? $extension : 'bin');

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $disk->put($path, $response->body(), 'public');

        return [
            'id' => hash('sha256', $path),
            'type' => $type,
            'url' => $disk->url($path),
            'original_name' => $this->originalName($attachment, $url),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, payload: array<string, mixed>}
     */
    private function send(SocialAccount $account, string $recipientId, array $message): array
    {
        $response = Http::withOptions(['force_ip_resolve' => 'v4'])
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([250, 1000], fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
            ->post($this->messageUrl($account), [
                'recipient' => ['id' => $recipientId],
                'message' => $message,
                'messaging_type' => 'RESPONSE',
                'access_token' => $account->access_token,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Facebook Messenger send failed: '.Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 500));
        }

        $messageId = data_get($response->json(), 'message_id');
        if (! is_string($messageId) || $messageId === '') {
            throw new RuntimeException('Facebook Messenger returned no message ID.');
        }

        return ['id' => $messageId, 'payload' => $response->json()];
    }

    private function uploadAttachment(SocialAccount $account, UploadedFile $file, string $type): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Facebook Messenger attachment could not be read from temporary storage.');
        }

        $response = Http::withOptions(['force_ip_resolve' => 'v4'])
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([250, 1000], fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
            ->attach('filedata', $file->getContent(), $file->getClientOriginalName(), [
                'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
            ])
            ->post($this->attachmentUrl($account), [
                'message' => json_encode([
                    'attachment' => [
                        'type' => $type,
                        'payload' => ['is_reusable' => true],
                    ],
                ], JSON_THROW_ON_ERROR),
                'access_token' => $account->access_token,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Facebook Messenger attachment upload failed: '.Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 500));
        }

        $attachmentId = data_get($response->json(), 'attachment_id');
        if (! is_string($attachmentId) || $attachmentId === '') {
            throw new RuntimeException('Facebook Messenger returned no attachment ID.');
        }

        return $attachmentId;
    }

    /** @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int} */
    private function attachmentMetadata(string $diskName, string $path, UploadedFile $file, string $id): array
    {
        return [
            'id' => $id,
            'type' => $this->attachmentType((string) $file->getMimeType()),
            'url' => Storage::disk($diskName)->url($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array{id: string, type: string, url: string, original_name: string, mime_type: string, size: int}
     */
    private function remoteAttachmentMetadata(array $attachment, string $url, string $type): array
    {
        return [
            'id' => (string) data_get($attachment, 'payload.attachment_id', hash('sha256', $url)),
            'type' => $type,
            'url' => $url,
            'original_name' => $this->originalName($attachment, $url),
            'mime_type' => 'application/octet-stream',
            'size' => 0,
        ];
    }

    /** @param array<string, mixed> $attachment */
    private function originalName(array $attachment, string $url): string
    {
        $name = data_get($attachment, 'payload.name', data_get($attachment, 'name'));

        return is_string($name) && $name !== ''
            ? $name
            : basename((string) parse_url($url, PHP_URL_PATH));
    }

    private function attachmentType(string $value): string
    {
        return match (true) {
            str_starts_with($value, 'image/') || $value === 'image',
            str_starts_with($value, 'video/') || $value === 'video',
            str_starts_with($value, 'audio/') || $value === 'audio' => Str::before($value, '/') ?: $value,
            default => 'file',
        };
    }

    private function messageUrl(SocialAccount $account): string
    {
        return rtrim((string) config('trypost.platforms.facebook.graph_api'), '/').'/'.$account->platform_user_id.'/messages';
    }

    private function attachmentUrl(SocialAccount $account): string
    {
        return rtrim((string) config('trypost.platforms.facebook.graph_api'), '/').'/'.$account->platform_user_id.'/message_attachments';
    }
}
