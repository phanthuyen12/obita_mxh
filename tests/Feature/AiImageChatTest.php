<?php

declare(strict_types=1);

use App\Jobs\ProcessAiImageChatJob;
use App\Models\ContentClonePreviewTask;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\ContentCloneGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    config(['services.hhtechapi.key' => 'test-hhtechapi-key']);
    Storage::fake();
});

test('ai-image-chat generate dispatches ProcessAiImageChatJob and returns task_id', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.ai-image-chat.generate'), [
            'prompt' => 'A coffee cup on a wooden table, warm morning sunlight',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['success', 'task_id']);

    Queue::assertPushed(ProcessAiImageChatJob::class);

    $task = ContentClonePreviewTask::query()->where('workspace_id', $this->workspace->id)->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('pending')
        ->and($task->payload['prompt'])->toBe('A coffee cup on a wooden table, warm morning sunlight');
});

test('ai-image-chat generate validates request payload', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.ai-image-chat.generate'), [
            'prompt' => '',
        ])
        ->assertInvalid(['prompt']);

    $this->actingAs($this->user)
        ->postJson(route('app.ai-image-chat.generate'), [
            'prompt' => 'valid prompt',
            'size' => '999x999',
        ])
        ->assertInvalid(['size']);

    $this->actingAs($this->user)
        ->postJson(route('app.ai-image-chat.generate'), [
            'prompt' => 'valid prompt',
            'quality' => 'ultra',
        ])
        ->assertInvalid(['quality']);

    Queue::assertNotPushed(ProcessAiImageChatJob::class);
});

test('ProcessAiImageChatJob generates image via hhtechapi and completes the task', function (): void {
    Http::fake([
        'hhtechapi.com/*' => Http::response([
            'data' => [
                ['b64_json' => base64_encode('fake-image-bytes')],
            ],
        ], 200),
    ]);

    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'prompt' => 'A coffee cup on a wooden table',
            'size' => '1024x1024',
            'quality' => 'medium',
        ],
    ]);

    (new ProcessAiImageChatJob($task))->handle(app(ContentCloneGenerator::class));

    expect($task->fresh()->status)->toBe('completed')
        ->and($task->fresh()->suggestions['url'])->not->toBeEmpty();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'hhtechapi.com/v1/images/generations')
            && $request['size'] === '1024x1024'
            && $request['quality'] === 'medium';
    });
});

test('ProcessAiImageChatJob maps size to portrait orientation and aspect ratio', function (): void {
    Http::fake([
        'hhtechapi.com/*' => Http::response([
            'data' => [
                ['b64_json' => base64_encode('fake-image-bytes')],
            ],
        ], 200),
    ]);

    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'prompt' => 'A coffee cup on a wooden table',
            'size' => '1024x1792',
        ],
    ]);

    (new ProcessAiImageChatJob($task))->handle(app(ContentCloneGenerator::class));

    expect($task->fresh()->status)->toBe('completed');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'hhtechapi.com/v1/images/generations')
            && $request['size'] === '1024x1792';
    });
});

test('ProcessAiImageChatJob falls back to stock image when hhtechapi fails', function (): void {
    Http::fake([
        'hhtechapi.com/*' => Http::response(['error' => ['message' => 'invalid key']], 401),
    ]);

    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'prompt' => 'A coffee cup on a wooden table',
        ],
    ]);

    (new ProcessAiImageChatJob($task))->handle(app(ContentCloneGenerator::class));

    expect($task->fresh()->status)->toBe('completed')
        ->and($task->fresh()->suggestions['url'])->toBe('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800');
});

test('AiImageClient uses hhtechapi first and falls back when it fails', function (): void {
    Http::fake([
        'hhtechapi.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429),
    ]);

    config(['ai.default_for_images' => 'openai']);

    $client = app(AiImageClient::class);
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('generateWithHhtechapi');
    $method->setAccessible(true);

    $result = $method->invoke($client, 'test prompt', '1024x1024', 'medium', 10);

    expect($result)->toBeNull();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'hhtechapi.com/v1/images/generations');
    });
});
