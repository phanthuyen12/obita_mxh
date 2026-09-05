<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContentClonePreviewTask;
use App\Models\Post;
use App\Services\ContentCloneGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessContentClonePreviewJob implements ShouldQueue
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

        try {
            $payload = $this->task->payload;

            $sourcePost = null;
            if (filled($payload['source_post_id'])) {
                $sourcePost = Post::query()
                    ->where('workspace_id', $this->task->workspace_id)
                    ->findOrFail($payload['source_post_id']);
            } elseif (filled($payload['manual_source_content'])) {
                $sourcePost = new Post([
                    'workspace_id' => $this->task->workspace_id,
                    'content' => $payload['manual_source_content'],
                    'media' => $payload['manual_source_media'] ?? [],
                ]);
            }

            if (! $sourcePost) {
                throw new \Exception('Không tìm thấy nội dung nguồn.');
            }

            $targetAccounts = $this->task->workspace->socialAccounts()
                ->active()
                ->whereIn('id', $payload['target_social_account_ids'])
                ->get();

            if ($targetAccounts->isEmpty()) {
                throw new \Exception('Chưa chọn trang đích đăng bài.');
            }

            $platform = $targetAccounts->first()->platform->value;

            $suggestions = $generator->previewSuggestions(
                workspace: $this->task->workspace,
                sourcePost: $sourcePost,
                theme: $payload['theme'] ?? null,
                prompt: $payload['prompt'] ?? null,
                imagePrompt: $payload['image_prompt'] ?? null,
                platform: $platform,
                aiImageCount: (int) ($payload['ai_image_count'] ?? 0),
                aiImageStyle: $payload['ai_image_style'] ?? null,
                aiLogoPath: $payload['ai_logo_path'] ?? null,
                diffContentPerPage: (bool) ($payload['diff_content_per_page'] ?? false),
                aiImageResolution: $payload['ai_image_resolution'] ?? null,
                aiImageAspectRatio: $payload['ai_image_aspect_ratio'] ?? null,
                aiContentMode: $payload['ai_content_mode'] ?? 'text_image',
                videoScenes: $payload['video_scenes'] ?? null,
                videoHook: $payload['video_hook'] ?? null,
                videoTargetDuration: isset($payload['video_target_duration']) ? (int) $payload['video_target_duration'] : null,
                characterName: $payload['character_name'] ?? null,
                characterDna: $payload['character_dna'] ?? null,
                characterAvatar: $payload['character_avatar'] ?? null,
            );

            $this->task->update([
                'status' => 'completed',
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable $e) {
            $this->task->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
