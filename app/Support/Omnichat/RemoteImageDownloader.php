<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class RemoteImageDownloader
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Download a remote image to a temporary file for the duration of the callback.
     *
     * @template TResult
     *
     * @param  Closure(UploadedFile): TResult  $callback
     * @return TResult
     */
    public function withDownloadedImage(string $url, Closure $callback): mixed
    {
        $this->assertSafeUrl($url);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'omnichat-ai-image-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary image file.');
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withOptions([
                    'allow_redirects' => false,
                    'sink' => $temporaryPath,
                    'on_headers' => function (ResponseInterface $response): void {
                        $contentLength = $response->getHeaderLine('Content-Length');
                        if ($contentLength !== '' && (int) $contentLength > self::MAX_FILE_SIZE) {
                            throw new RuntimeException('The remote image exceeds the 10 MB limit.');
                        }
                    },
                    'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                        if ($downloadedBytes > self::MAX_FILE_SIZE) {
                            throw new RuntimeException('The remote image exceeds the 10 MB limit.');
                        }
                    },
                ])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('The remote image could not be downloaded.');
            }

            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

            if (! is_string($mimeType) || ! isset($extensions[$mimeType]) || @getimagesize($temporaryPath) === false) {
                throw new RuntimeException('The remote file is not a supported image type.');
            }

            $fileSize = filesize($temporaryPath);
            if ($fileSize === false || $fileSize < 1 || $fileSize > self::MAX_FILE_SIZE) {
                throw new RuntimeException('The remote image is empty or exceeds the 10 MB limit.');
            }

            $originalName = basename((string) parse_url($url, PHP_URL_PATH));
            if ($originalName === '' || $originalName === '.' || $originalName === '/') {
                $originalName = 'ai-image.'.$extensions[$mimeType];
            }

            $image = new UploadedFile($temporaryPath, $originalName, $mimeType, null, true);

            return $callback($image);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)
            || preg_match('/\.(local|localhost|internal)$/i', $host) === 1) {
            throw new RuntimeException('The image URL must use a public HTTPS address.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new RuntimeException('Private network image URLs are not allowed.');
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Private network image URLs are not allowed.');
            }
        }
    }
}
