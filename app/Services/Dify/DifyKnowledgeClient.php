<?php

declare(strict_types=1);

namespace App\Services\Dify;

use App\Exceptions\DifyWorkflowException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DifyKnowledgeClient
{
    /**
     * Tạo tài liệu mới trong Dify Knowledge Base bằng văn bản thuần (Text/FAQ/Chính sách)
     * POST /v1/datasets/{dataset_id}/document/create-by-text
     *
     * @return array{document: array<string, mixed>, batch: string}
     */
    public function createDocumentByText(
        string $datasetId,
        string $name,
        string $text,
        ?string $apiKey = null,
        ?string $baseUrl = null,
    ): array {
        $key = $apiKey ?: (string) config('services.dify.dataset_api_key', config('services.dify.api_key'));
        if (blank($key)) {
            throw new DifyWorkflowException('Dify Dataset API key is not configured.');
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        $payload = [
            'name' => $name,
            'text' => $text,
            'indexing_technique' => 'high_quality',
            'process_rule' => [
                'mode' => 'automatic',
            ],
        ];

        $response = $this->client($key, $url)
            ->post("/datasets/{$datasetId}/document/create-by-text", $payload);

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?: $response->body());
            Log::error('Dify createDocumentByText failed', [
                'status' => $response->status(),
                'dataset_id' => $datasetId,
                'error' => $message,
            ]);
            throw new DifyWorkflowException("Đẩy văn bản lên Dify Knowledge thất bại ({$response->status()}): {$message}");
        }

        return $response->json();
    }

    /**
     * Tạo tài liệu mới trong Dify Knowledge Base bằng File (PDF, DOCX, TXT, CSV...)
     * POST /v1/datasets/{dataset_id}/document/create-by-file
     *
     * @return array{document: array<string, mixed>, batch: string}
     */
    public function createDocumentByFile(
        string $datasetId,
        UploadedFile $file,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $customName = null,
    ): array {
        $key = $apiKey ?: (string) config('services.dify.dataset_api_key', config('services.dify.api_key'));
        if (blank($key)) {
            throw new DifyWorkflowException('Dify Dataset API key is not configured.');
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        $processData = [
            'indexing_technique' => 'high_quality',
            'process_rule' => [
                'mode' => 'automatic',
            ],
        ];

        $fileName = $customName ?: $file->getClientOriginalName();

        $response = $this->client($key, $url)
            ->attach('file', (string) file_get_contents($file->getRealPath()), $fileName)
            ->post("/datasets/{$datasetId}/document/create-by-file", [
                'data' => json_encode($processData),
            ]);

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?: $response->body());
            Log::error('Dify createDocumentByFile failed', [
                'status' => $response->status(),
                'dataset_id' => $datasetId,
                'error' => $message,
            ]);
            throw new DifyWorkflowException("Upload file lên Dify Knowledge thất bại ({$response->status()}): {$message}");
        }

        return $response->json();
    }

    /**
     * Xóa tài liệu khỏi Dify Knowledge Base
     * DELETE /v1/datasets/{dataset_id}/documents/{document_id}
     */
    public function deleteDocument(
        string $datasetId,
        string $documentId,
        ?string $apiKey = null,
        ?string $baseUrl = null,
    ): bool {
        $key = $apiKey ?: (string) config('services.dify.dataset_api_key', config('services.dify.api_key'));
        if (blank($key)) {
            return false;
        }

        $url = $baseUrl ?: (string) config('services.dify.base_url', 'https://api.dify.ai/v1');

        $response = $this->client($key, $url)
            ->delete("/datasets/{$datasetId}/documents/{$documentId}");

        return $response->successful();
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
