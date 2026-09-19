<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for Anthropic-compatible Chat API.
 *
 * Compatible with endpoints following the Anthropic Messages API specification:
 *   POST /v1/messages
 *   Authorization: Bearer <api_key>
 *   anthropic-version: 2023-06-01
 *
 * Used for providers like hhtechapi.com that mirror the Anthropic API.
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
class AnthropicChatClient
{
    public const DEFAULT_BASE_URL = 'https://hhtechapi.com/v1';

    public const DEFAULT_MODEL = 'claude-sonnet-4-5';

    public const DEFAULT_MAX_TOKENS = 1024;

    /**
     * Send a message and get a reply from the Anthropic-compatible API.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Previous conversation turns
     */
    public function sendMessage(
        string $userMessage,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        array $history = [],
        ?string $systemPrompt = null,
        int $maxTokens = self::DEFAULT_MAX_TOKENS,
    ): ?string {
        $key = $apiKey ?: (string) config('services.anthropic.api_key', '');
        if (blank($key)) {
            Log::warning('[AnthropicChat] API key is not configured.');

            return null;
        }

        $url = rtrim($baseUrl ?: (string) config('services.anthropic.base_url', self::DEFAULT_BASE_URL), '/');
        $resolvedModel = $model ?: (string) config('services.anthropic.model', self::DEFAULT_MODEL);

        // Build messages array: history + current user message
        $messages = [];
        foreach ($history as $turn) {
            if (! empty($turn['role']) && ! empty($turn['content'])) {
                $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => $resolvedModel,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if (! blank($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        Log::info('[AnthropicChat] Sending request', [
            'url' => $url.'/messages',
            'model' => $resolvedModel,
            'turns' => count($messages),
        ]);

        try {
            $response = Http::withToken($key)
                ->withHeaders([
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->baseUrl($url)
                ->connectTimeout(15)
                ->timeout(120)
                ->post('/messages', $payload);

            if (! $response->successful()) {
                Log::error('[AnthropicChat] Request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            // Anthropic response: { content: [{ type: 'text', text: '...' }] }
            $text = data_get($data, 'content.0.text');
            if (! is_string($text) || blank($text)) {
                Log::warning('[AnthropicChat] Empty or invalid response content', ['data' => $data]);

                return null;
            }

            Log::info('[AnthropicChat] Reply received', ['length' => mb_strlen($text)]);

            return $text;
        } catch (\Throwable $e) {
            Log::error('[AnthropicChat] Exception during request', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Test connectivity by sending a simple ping message.
     *
     * @return array{success: bool, message: string, model?: string}
     */
    public function testConnection(?string $apiKey = null, ?string $baseUrl = null, ?string $model = null): array
    {
        $reply = $this->sendMessage(
            userMessage: 'Xin chào! Hãy trả lời bằng tiếng Việt trong 1 câu ngắn.',
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            model: $model,
            maxTokens: 64,
        );

        if ($reply !== null) {
            return [
                'success' => true,
                'message' => 'Kết nối thành công! Model phản hồi: '.$reply,
                'model' => $model ?? self::DEFAULT_MODEL,
            ];
        }

        return [
            'success' => false,
            'message' => 'Không thể kết nối đến API. Kiểm tra lại API Key và Base URL.',
        ];
    }
}
