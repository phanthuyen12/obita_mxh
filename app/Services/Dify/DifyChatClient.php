<?php

declare(strict_types=1);

namespace App\Services\Dify;

use App\Exceptions\DifyWorkflowException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DifyChatClient
{
    /**
     * Send a message to Dify Chatbot API (/chat-messages)
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function sendMessage(
        string $query,
        ?string $conversationId = null,
        string $user = 'omnichat-user',
        array $inputs = [],
        ?string $apiKey = null,
        ?string $baseUrl = null,
    ): array {
        $key = $apiKey ?: (string) config('services.dify.api_key');
        if (blank($key)) {
            throw new DifyWorkflowException('Dify API key is not configured.');
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        $payload = [
            'inputs' => empty($inputs) ? new \stdClass : $inputs,
            'query' => $query,
            'response_mode' => 'blocking',
            'user' => $user,
        ];

        if ($conversationId !== null && $conversationId !== '') {
            $payload['conversation_id'] = $conversationId;
        }

        $response = $this->client($key, $url)->post('/chat-messages', $payload);

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?: $response->body());
            Log::error('Dify chat request failed', [
                'status' => $response->status(),
                'error' => $message,
            ]);
            throw new DifyWorkflowException("Dify chat request failed with HTTP {$response->status()}: {$message}");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new DifyWorkflowException('Dify returned an invalid chat response.');
        }

        return $data;
    }

    /**
     * Test connection to Dify App API
     *
     * @return array{success: bool, message: string, bot_name?: string}
     */
    public function testConnection(?string $apiKey = null, ?string $baseUrl = null): array
    {
        $key = $apiKey ?: (string) config('services.dify.api_key');
        if (blank($key)) {
            return [
                'success' => false,
                'message' => 'Chưa cấu hình API Key của Dify.',
            ];
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        try {
            // First try GET /info or /parameters
            $infoResponse = $this->client($key, $url)->get('/parameters');
            if ($infoResponse->successful()) {
                $info = $infoResponse->json();

                return [
                    'success' => true,
                    'message' => 'Kết nối Dify Chatbot thành công!',
                    'bot_name' => $info['user_input_form'] ? 'Dify App Connected' : 'Alen Coffee Advisor',
                ];
            }

            // Fallback: Try a ping message
            $chatResponse = $this->client($key, $url)->post('/chat-messages', [
                'inputs' => new \stdClass,
                'query' => 'ping',
                'response_mode' => 'blocking',
                'user' => 'health-check',
            ]);

            if ($chatResponse->successful()) {
                return [
                    'success' => true,
                    'message' => 'Kết nối và giao tiếp với Dify Chatbot thành công!',
                    'bot_name' => 'Alen Coffee Advisor',
                ];
            }

            $error = $chatResponse->json('message') ?: "HTTP {$chatResponse->status()}";

            return [
                'success' => false,
                'message' => 'Không thể kết nối đến Dify. Vui lòng kiểm tra lại URL và API Key.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối Dify: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Upload a file to Dify API (/files/upload)
     *
     * @return array{id: string, name: string, size: int, extension: string, mime_type: string}
     */
    public function uploadFile(
        UploadedFile $file,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        string $user = 'admin',
    ): array {
        $key = $apiKey ?: (string) config('services.dify.api_key');
        if (blank($key)) {
            throw new DifyWorkflowException('Dify API key is not configured.');
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        $response = $this->client($key, $url)
            ->attach('file', (string) file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post('/files/upload', [
                'user' => $user,
            ]);

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?: $response->body());
            Log::error('Dify file upload failed', [
                'status' => $response->status(),
                'error' => $message,
            ]);
            throw new DifyWorkflowException("Upload file lên Dify thất bại ({$response->status()}): {$message}");
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['id'])) {
            throw new DifyWorkflowException('Dify không trả về mã định danh file (ID) hợp lệ.');
        }

        return $data;
    }

    private function client(string $apiKey, string $baseUrl): PendingRequest
    {
        return Http::withToken($apiKey)
            ->baseUrl(rtrim($baseUrl, '/'))
            ->connectTimeout((int) config('services.dify.connect_timeout', 15))
            ->timeout((int) config('services.dify.timeout', 120))
            ->acceptJson();
    }
}
