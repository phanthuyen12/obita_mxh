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

class ProcessContentCloneSceneImageJob implements ShouldQueue
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

        $fallbackUrl = 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=800&auto=format&fit=crop&q=80';

        try {
            $workspace = $this->task->workspace;
            $payload = $this->task->payload;

            $aiImage = app(AiImageClient::class);
            $styleVal = $payload['style'] ?? $workspace->image_style;
            $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);
            $orientation = $generator->resolveOrientation($payload['aspect_ratio'] ?? '9:16', 'tiktok');

            $characterPrompt = collect([
                filled($payload['character_name'] ?? null) || filled($payload['character_dna'] ?? null)
                    ? 'Featuring character '.($payload['character_name'] ?? 'King Coffee Brand Ambassador').': '.($payload['character_dna'] ?? '')
                    : null,
                filled($payload['character_avatar'] ?? null)
                    ? 'Consistent facial appearance matching reference avatar: '.$payload['character_avatar']
                    : null,
            ])->filter()->implode('. ');

            $finalPrompt = collect([
                $payload['prompt'] ?? '',
                $characterPrompt,
            ])->filter()->implode('. ');

            $imageResult = $aiImage->generate(
                keywords: [$payload['theme'] ?? 'King Coffee'],
                style: $style,
                orientation: $orientation,
                language: $workspace->content_language ?? 'en',
                customPrompt: $finalPrompt,
                logoPath: $payload['logo_path'] ?: null,
                customResolution: $payload['resolution'] ?: null,
                customAspectRatio: $payload['aspect_ratio'] ?: '9:16',
            );

            if ($imageResult !== null) {
                $imageBytes = $imageResult['bytes'];
                if (filled($payload['logo_path'] ?? null)) {
                    $imageBytes = $generator->overlayLogo($imageBytes, $payload['logo_path']);
                }
                $saved = $generator->saveMediaToUserFolder($workspace, $imageBytes, 'video-scene-'.uniqid().'.jpg');

                $this->task->update([
                    'status' => 'completed',
                    'suggestions' => [
                        'url' => $saved['url'],
                    ],
                ]);

                return;
            }

            // Fallback if AI image client returned null
            $this->task->update([
                'status' => 'completed',
                'suggestions' => [
                    'url' => $fallbackUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessContentCloneSceneImageJob failed: '.$e->getMessage());

            $this->task->update([
                'status' => 'completed',
                'suggestions' => [
                    'url' => $fallbackUrl,
                ],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
