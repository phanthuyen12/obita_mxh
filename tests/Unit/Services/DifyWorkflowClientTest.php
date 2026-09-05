<?php

declare(strict_types=1);

use App\Exceptions\DifyWorkflowException;
use App\Services\Dify\DifyWorkflowClient;
use Illuminate\Support\Facades\Http;

test('dify workflow client sends inputs and returns workflow outputs', function (): void {
    config([
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => [
                'status' => 'succeeded',
                'outputs' => ['content' => 'Nội dung mới từ Dify.'],
            ],
        ]),
    ]);

    $outputs = app(DifyWorkflowClient::class)->run(['source_content' => 'Bài nguồn'], 'workspace:test');

    expect($outputs)->toBe(['content' => 'Nội dung mới từ Dify.']);
    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer app-test-key')
            && $request['response_mode'] === 'blocking'
            && $request['user'] === 'workspace:test'
            && $request['inputs']['source_content'] === 'Bài nguồn';
    });
});

test('dify workflow client reports workflow errors', function (): void {
    config([
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => ['status' => 'failed', 'error' => 'Workflow lỗi'],
        ]),
    ]);

    expect(fn (): array => app(DifyWorkflowClient::class)->run([], 'workspace:test'))
        ->toThrow(DifyWorkflowException::class, 'Workflow lỗi');
});

test('dify workflow client falls back to chat-messages when app mode is advanced-chat', function (): void {
    config([
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-advanced-chat-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'code' => 'not_workflow_app',
            'message' => 'Please check if your app mode matches the right API route.',
            'status' => 400,
        ], 400),
        'https://dify.example/v1/chat-messages' => Http::response([
            'event' => 'message',
            'mode' => 'advanced-chat',
            'answer' => 'Nội dung King Coffee từ Advanced Chat.',
            'files' => [],
        ]),
    ]);

    $outputs = app(DifyWorkflowClient::class)->run(['requirement' => 'Tạo bài viết King Coffee'], 'workspace:test');

    expect($outputs['content'])->toBe('Nội dung King Coffee từ Advanced Chat.');
});
