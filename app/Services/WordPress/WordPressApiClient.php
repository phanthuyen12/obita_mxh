<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use App\Enums\WordPress\SiteStatus;
use App\Models\WordPressSite;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressApiClient
{
    /**
     * Format and normalize WordPress base URL (strip trailing slashes, ensure https/http).
     */
    public function formatBaseUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    /**
     * Send request to WordPress REST API with automatic permalink fallback (/wp-json/ vs ?rest_route=).
     */
    public function sendRequest(
        string $url,
        string $username,
        string $password,
        string $endpoint,
        string $method = 'get',
        array $params = [],
    ): Response {
        $baseUrl = $this->formatBaseUrl($url);
        $cleanPassword = str_replace(' ', '', trim($password));
        $cleanEndpoint = ltrim($endpoint, '/');

        $httpClient = Http::withBasicAuth(trim($username), $cleanPassword)->timeout(15);

        // 1. Try standard permalink route: /wp-json/wp/v2/...
        $standardUrl = "{$baseUrl}/wp-json/wp/v2/{$cleanEndpoint}";
        $response = match (strtolower($method)) {
            'post' => $httpClient->post($standardUrl, $params),
            default => $httpClient->get($standardUrl, $params),
        };

        // If standard route succeeds or returns authentication error (401/403), return it
        if ($response->successful() || in_array($response->status(), [401, 403], true)) {
            return $response;
        }

        // 2. If 404 (common on sites with Plain Permalinks or LiteSpeed without rewrite), fallback to ?rest_route=/wp/v2/...
        $fallbackUrl = "{$baseUrl}/index.php";
        $restRoute = "/wp/v2/{$cleanEndpoint}";

        return match (strtolower($method)) {
            'post' => $httpClient
                ->withQueryParameters(['rest_route' => $restRoute])
                ->post($fallbackUrl, $params),
            default => $httpClient->get(
                $fallbackUrl,
                array_merge(['rest_route' => $restRoute], $params),
            ),
        };
    }

    /**
     * Test and verify connection to WordPress REST API using Application Passwords.
     *
     * @return array{success: bool, user_id?: int|null, user_name?: string|null, error?: string}
     */
    public function verifyConnection(string $url, string $username, string $applicationPassword): array
    {
        try {
            $response = $this->sendRequest($url, $username, $applicationPassword, 'users/me');

            if ($response->successful()) {
                $user = $response->json();

                return [
                    'success' => true,
                    'user_id' => $user['id'] ?? null,
                    'user_name' => $user['name'] ?? $username,
                ];
            }

            $errorMessage = $response->json('message') ?? 'HTTP '.$response->status().' - Xác thực tài khoản không thành công.';

            return [
                'success' => false,
                'error' => (string) $errorMessage,
            ];
        } catch (Exception $e) {
            Log::warning('WordPress test connection failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Không thể kết nối đến website: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync categories and tags from WordPress REST API.
     */
    public function syncTaxonomies(WordPressSite $site): bool
    {
        try {
            // 1. Fetch Categories
            $catResponse = $this->sendRequest(
                $site->url,
                $site->username,
                (string) $site->application_password,
                'categories',
                'get',
                ['per_page' => 100],
            );

            $categories = $catResponse->successful()
                ? collect($catResponse->json())->map(fn ($c) => [
                    'id' => (int) $c['id'],
                    'name' => (string) $c['name'],
                    'slug' => (string) $c['slug'],
                    'count' => (int) ($c['count'] ?? 0),
                ])->values()->all()
                : [];

            // 2. Fetch Tags
            $tagResponse = $this->sendRequest(
                $site->url,
                $site->username,
                (string) $site->application_password,
                'tags',
                'get',
                ['per_page' => 100],
            );

            $tags = $tagResponse->successful()
                ? collect($tagResponse->json())->map(fn ($t) => [
                    'id' => (int) $t['id'],
                    'name' => (string) $t['name'],
                    'slug' => (string) $t['slug'],
                ])->values()->all()
                : [];

            $site->update([
                'categories_cache' => $categories,
                'tags_cache' => $tags,
                'status' => SiteStatus::Connected,
                'error_message' => null,
                'last_synced_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('WordPress sync taxonomies failed', [
                'site_id' => $site->id,
                'error' => $e->getMessage(),
            ]);

            $site->update([
                'status' => SiteStatus::Error,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Upload an image to WordPress Media Library.
     */
    public function uploadMedia(
        string $url,
        string $username,
        string $password,
        string $fileUrlOrPath,
        ?string $filename = null,
    ): ?int {
        try {
            $fileContents = null;
            if (preg_match('#^https?://#i', $fileUrlOrPath)) {
                $fileContents = Http::timeout(20)->get($fileUrlOrPath)->body();
            } elseif (file_exists($fileUrlOrPath)) {
                $fileContents = file_get_contents($fileUrlOrPath);
            }

            if (! $fileContents) {
                return null;
            }

            $name = $filename ?: basename(parse_url($fileUrlOrPath, PHP_URL_PATH) ?: 'image.jpg');
            if (! str_contains($name, '.')) {
                $name .= '.jpg';
            }

            $baseUrl = $this->formatBaseUrl($url);
            $cleanPassword = str_replace(' ', '', trim($password));

            $response = Http::withBasicAuth(trim($username), $cleanPassword)
                ->timeout(30)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="'.$name.'"',
                ])
                ->withBody($fileContents, 'image/jpeg')
                ->post("{$baseUrl}/wp-json/wp/v2/media");

            if ($response->successful()) {
                return (int) ($response->json('id') ?? null);
            }

            // Fallback for plain permalinks
            $fallbackResponse = Http::withBasicAuth(trim($username), $cleanPassword)
                ->timeout(30)
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="'.$name.'"',
                ])
                ->withBody($fileContents, 'image/jpeg')
                ->post("{$baseUrl}/index.php?rest_route=/wp/v2/media");

            if ($fallbackResponse->successful()) {
                return (int) ($fallbackResponse->json('id') ?? null);
            }

            return null;
        } catch (Exception $e) {
            Log::warning('WordPress upload media failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
