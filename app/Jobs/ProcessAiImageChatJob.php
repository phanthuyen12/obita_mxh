<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Workspace\ImageStyle;
use App\Models\ContentClonePreviewTask;
use App\Services\Ai\AiImageClient;
use App\Services\ContentCloneGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiImageChatJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContentClonePreviewTask $task) {}

    /**
     * Execute the job.
     */
    public function handle(ContentCloneGenerator $generator): void
    {
        $this->task->update(['status' => 'processing']);

        $fallbackUrl = 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800';

        try {
            $workspace = $this->task->workspace;
            $payload = $this->task->payload;

            $size = data_get($payload, 'size', '1024x1024');
            $orientation = match ($size) {
                '1024x1792' => 'portrait',
                '1792x1024' => 'landscape',
                default => 'square',
            };

            $result = app(AiImageClient::class)->generate(
                keywords: [data_get($payload, 'theme', 'AI Generated Image')],
                style: ImageStyle::DEFAULT,
                orientation: $orientation,
                customPrompt: data_get($payload, 'prompt'),
                quality: data_get($payload, 'quality', 'medium'),
                customAspectRatio: match ($size) {
                    '1024x1792' => '9:16',
                    '1792x1024' => '16:9',
                    default => '1:1',
                },
            );

            if ($result !== null) {
                $saved = $generator->saveMediaToUserFolder(
                    $workspace,
                    $result['bytes'],
                    'ai-chat-image-'.uniqid().'.jpg',
                );

                $this->task->update([
                    'status' => 'completed',
                    'suggestions' => ['url' => $saved['url']],
                ]);

                return;
            }

            $this->task->update([
                'status' => 'completed',
                'suggestions' => ['url' => $fallbackUrl],
            ]);
        } catch (Throwable $e) {
            Log::warning('ProcessAiImageChatJob failed: '.$e->getMessage());

            $this->task->update([
                'status' => 'completed',
                'suggestions' => ['url' => $fallbackUrl],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
