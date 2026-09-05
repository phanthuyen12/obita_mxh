<?php

declare(strict_types=1);

namespace App\Services\Dify;

use App\Exceptions\DifyWorkflowException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DifyWorkflowClient
{
    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function run(array $inputs, string $user, ?string $apiKey = null): array
    {
        $key = $apiKey ?: (string) config('services.dify.api_key');

        if ($key === '') {
            throw new DifyWorkflowException('Dify API key is not configured.');
        }

        Log::info('DifyWorkflowClient: Gửi request đến Dify API (workflows/run)', [
            'endpoint' => 'workflows/run',
            'inputs' => $inputs,
            'user' => $user,
        ]);

        // 1. First attempt: Standard Workflow API (POST /v1/workflows/run)
        $response = $this->request($key)->post('workflows/run', [
            'inputs' => $inputs,
            'response_mode' => 'blocking',
            'user' => $user,
        ]);

        Log::info('DifyWorkflowClient: Dify trả về HTTP response (workflows/run)', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->json() ?? $response->body(),
        ]);

        if ($response->successful()) {
            $payload = $response->json();
            $data = data_get($payload, 'data', $payload);

            if (! is_array($data)) {
                throw new DifyWorkflowException('Dify returned an invalid workflow response.');
            }

            $status = data_get($data, 'status');

            if ($status !== null && $status !== 'succeeded') {
                $message = (string) (data_get($data, 'error') ?: 'Dify workflow did not succeed.');

                throw new DifyWorkflowException($message);
            }

            $outputs = data_get($data, 'outputs');

            if (! is_array($outputs)) {
                throw new DifyWorkflowException('Dify workflow returned no outputs.');
            }

            return $outputs;
        }

        $message = (string) ($response->json('message') ?? $response->reason());

        // 2. If app mode mismatch (e.g. Advanced Chat / Agent App), fallback to /v1/chat-messages
        if ($response->status() === 400 && str_contains(strtolower($message), 'app mode')) {
            $queryText = (string) ($inputs['requirement'] ?? $inputs['instructions'] ?? $inputs['source_content'] ?? 'Sáng tạo nội dung');

            // Strip non-scalar inputs (e.g. Product file arrays) for chat-messages inputs
            $scalarInputs = [];
            $files = [];
            foreach ($inputs as $k => $v) {
                if ($k === 'Product' && is_array($v)) {
                    $files = $v;
                } elseif (is_scalar($v) || is_null($v)) {
                    $scalarInputs[$k] = (string) ($v ?? '');
                }
            }

            $chatBody = [
                'inputs' => $scalarInputs,
                'query' => $queryText,
                'response_mode' => 'blocking',
                'user' => $user,
            ];
            if (! empty($files)) {
                $chatBody['files'] = $files;
            }

            $chatResponse = $this->request($key)->post('chat-messages', $chatBody);

            if ($chatResponse->successful()) {
                $chatPayload = $chatResponse->json();
                $answer = (string) (data_get($chatPayload, 'answer') ?? '');
                $files = (array) (data_get($chatPayload, 'files') ?? []);

                return [
                    'result' => $answer,
                    'text' => $answer,
                    'content' => $answer,
                    'answer' => $answer,
                    'files' => $files,
                    'data' => $chatPayload,
                ];
            }

            // Also try with empty inputs object if chat app has no defined variables
            $chatResponseEmptyInputs = $this->request($key)->post('chat-messages', [
                'inputs' => (object) [],
                'query' => $queryText,
                'response_mode' => 'blocking',
                'user' => $user,
            ]);

            if ($chatResponseEmptyInputs->successful()) {
                $chatPayload = $chatResponseEmptyInputs->json();
                $answer = (string) (data_get($chatPayload, 'answer') ?? '');
                $files = (array) (data_get($chatPayload, 'files') ?? []);

                return [
                    'result' => $answer,
                    'text' => $answer,
                    'content' => $answer,
                    'answer' => $answer,
                    'files' => $files,
                    'data' => $chatPayload,
                ];
            }

            // 3. Fallback to /v1/completion-messages if it is a Completion App
            $compResponse = $this->request($key)->post('completion-messages', [
                'inputs' => $scalarInputs,
                'response_mode' => 'blocking',
                'user' => $user,
            ]);

            if ($compResponse->successful()) {
                $compPayload = $compResponse->json();
                $answer = (string) (data_get($compPayload, 'answer') ?? '');

                return [
                    'result' => $answer,
                    'text' => $answer,
                    'content' => $answer,
                    'answer' => $answer,
                    'data' => $compPayload,
                ];
            }
        }

        throw new DifyWorkflowException(
            "Dify workflow request failed with HTTP {$response->status()}: {$message}",
        );
    }

    private function request(string $apiKey): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.dify.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.dify.connect_timeout', 10))
            ->timeout((int) config('services.dify.timeout', 120));
    }
}
