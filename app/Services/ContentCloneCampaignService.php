<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Post\CreatePost;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\Workspace\ImageStyle;
use App\Jobs\ProcessContentCloneCampaignJob;
use App\Models\ContentCloneCampaign;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\Workflow\ContentWorkflowNotifier;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContentCloneCampaignService
{
    public function __construct(
        private ContentCloneGenerator $generator,
        private ContentWorkflowNotifier $workflowNotifier,
    ) {}

    /**
     * @param  array<int, string>  $targetSocialAccountIds
     * @param  array<int, array<string, mixed>>|null  $initialMedia
     */
    public function create(
        Workspace $workspace,
        User $user,
        Post $sourcePost,
        array $targetSocialAccountIds,
        ?string $workflowId,
        int $totalPosts,
        int $intervalDays,
        DateTimeInterface $startAt,
        ?string $theme = null,
        ?string $prompt = null,
        ?string $imagePrompt = null,
        ?string $initialContent = null,
        ?array $initialMedia = null,
        bool $requireApproval = true,
        int $aiImageCount = 0,
        ?string $aiImageStyle = null,
        ?string $aiLogoPath = null,
        bool $diffContentPerPage = false,
        ?string $aiImageResolution = null,
        ?string $aiImageAspectRatio = null,
        ?string $aiContentMode = null,
        ?array $videoScenes = null,
    ): ContentCloneCampaign {
        $startAt = Carbon::instance($startAt)->utc();

        $campaign = $workspace->contentCloneCampaigns()->create([
            'source_post_id' => $sourcePost->id,
            'content_workflow_id' => $workflowId,
            'created_by' => $user->id,
            'target_social_account_ids' => array_values($targetSocialAccountIds),
            'theme' => $theme,
            'prompt' => $prompt,
            'image_prompt' => $imagePrompt,
            'ai_image_count' => $aiImageCount,
            'ai_image_style' => $aiImageStyle,
            'ai_image_resolution' => $aiImageResolution,
            'ai_image_aspect_ratio' => $aiImageAspectRatio,
            'ai_logo_path' => $aiLogoPath,
            'diff_content_per_page' => $diffContentPerPage,
            'ai_content_mode' => $aiContentMode ?? 'text_image',
            'video_scenes' => $videoScenes,
            'initial_content' => $initialContent,
            'initial_media' => $initialMedia,
            'total_posts' => $totalPosts,
            'generated_posts' => 0,
            'interval_days' => $intervalDays,
            'start_at' => $startAt->utc(),
            'next_run_at' => $startAt->utc(),
            'require_approval' => $requireApproval,
            'is_active' => true,
        ]);

        if (! app()->runningUnitTests()) {
            $claimed = DB::transaction(function () use ($campaign): bool {
                $locked = ContentCloneCampaign::query()->lockForUpdate()->find($campaign->id);
                if (! $locked || ! $locked->is_active) {
                    return false;
                }
                $locked->update(['next_run_at' => now()->addMinutes(10)]);

                return true;
            });

            if ($claimed) {
                ProcessContentCloneCampaignJob::dispatch($campaign);
            }
        }

        return $campaign;
    }

    public function processDue(): int
    {
        $processed = 0;

        ContentCloneCampaign::query()
            ->due()
            ->with(['workspace', 'sourcePost', 'contentWorkflow'])
            ->orderBy('next_run_at')
            ->each(function (ContentCloneCampaign $campaign) use (&$processed): void {
                $claimed = DB::transaction(function () use ($campaign): bool {
                    $locked = ContentCloneCampaign::query()->lockForUpdate()->find($campaign->id);

                    if (! $locked || ! $locked->is_active || $locked->generated_posts >= $locked->total_posts || $locked->next_run_at->isFuture()) {
                        return false;
                    }

                    // Reserve this slot while AI generation is running so two cron
                    // workers cannot create the same day's clone.
                    $locked->update(['next_run_at' => now()->addMinutes(10)]);

                    return true;
                });

                if ($claimed) {
                    ProcessContentCloneCampaignJob::dispatch($campaign);
                    $processed++;
                }
            });

        return $processed;
    }

    public function processOne(ContentCloneCampaign $campaign): bool
    {
        $claimed = DB::transaction(function () use ($campaign): bool {
            $locked = ContentCloneCampaign::query()->lockForUpdate()->find($campaign->id);

            if (! $locked || ! $locked->is_active || $locked->generated_posts >= $locked->total_posts || $locked->next_run_at->isFuture()) {
                return false;
            }

            // Reserve this slot while AI generation is running so two cron
            // workers cannot create the same day's clone.
            $locked->update(['next_run_at' => now()->addMinutes(10)]);

            return true;
        });

        if (! $claimed) {
            return false;
        }

        return $this->executeCampaignRun($campaign);
    }

    public function executeCampaignRun(ContentCloneCampaign $campaign): bool
    {
        $campaign->refresh();

        try {
            while ($campaign->generated_posts < $campaign->total_posts && $campaign->is_active) {
                $post = $this->createClone($campaign);
                $this->markCompleted($campaign);

                if ($campaign->require_approval) {
                    $this->workflowNotifier->notifyReviewers($post);
                }

                $campaign->refresh();
            }

            return true;
        } catch (\Throwable $exception) {
            $campaign->update([
                'next_run_at' => now()->addMinutes(5),
                'last_error' => $exception->getMessage(),
            ]);

            Log::error('Content clone campaign failed', [
                'campaign_id' => $campaign->id,
                'source_post_id' => $campaign->source_post_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function createClone(ContentCloneCampaign $campaign): Post
    {
        $sourcePost = $campaign->sourcePost->loadMissing('workspace', 'postPlatforms.socialAccount');
        $targetAccounts = $sourcePost->workspace->socialAccounts()
            ->active()
            ->whereIn('id', $campaign->target_social_account_ids)
            ->get();

        if ($targetAccounts->isEmpty()) {
            throw new \RuntimeException('Campaign không còn page đích đang hoạt động.');
        }

        $platform = $targetAccounts->first()->platform->value;

        $content = $this->resolveContent($campaign, $sourcePost, $platform);
        $keywords = [];

        if ($campaign->ai_image_count > 0) {
            try {
                $generated = $this->generator->generateStructured(
                    workspace: $sourcePost->workspace,
                    sourcePost: $sourcePost,
                    theme: $campaign->theme,
                    prompt: $campaign->prompt,
                    platform: $platform,
                    aiContentMode: $campaign->ai_content_mode ?? 'text_image',
                );
                $keywords = $generated['image_keywords'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('Failed to generate image keywords: '.$e->getMessage());
            }
        }

        // Resolve media
        $media = $sourcePost->media ?? [];
        if ($campaign->generated_posts === 0 && filled($campaign->initial_media)) {
            $media = $campaign->initial_media;
        } elseif ($campaign->ai_image_count > 0 && ! empty($keywords)) {
            $generatedImages = [];
            $imageTitle = $generated['image_title'] ?? '';
            $imageBody = $generated['image_body'] ?? '';

            $finalImagePrompt = $this->generator->buildFinalImagePrompt(
                $sourcePost->workspace,
                $campaign->image_prompt,
                $campaign->theme,
                $imageTitle,
                $imageBody
            );

            $orientation = $this->generator->resolveOrientation($campaign->ai_image_aspect_ratio, $platform);
            $styleVal = $campaign->ai_image_style ?? $sourcePost->workspace->image_style;
            $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);

            for ($i = 0; $i < $campaign->ai_image_count; $i++) {
                $imageResult = app(AiImageClient::class)->generate(
                    keywords: $keywords,
                    style: $style,
                    orientation: $orientation,
                    language: $sourcePost->workspace->content_language ?? 'en',
                    customPrompt: $finalImagePrompt,
                    logoPath: $campaign->ai_logo_path ?: null,
                    customResolution: $campaign->ai_image_resolution ?: null,
                    customAspectRatio: $campaign->ai_image_aspect_ratio ?: null,
                );

                if ($imageResult !== null) {
                    $imageBytes = $imageResult['bytes'];
                    if (filled($campaign->ai_logo_path)) {
                        $imageBytes = $this->generator->overlayLogo($imageBytes, $campaign->ai_logo_path);
                    }

                    $savedMedia = $this->generator->saveMediaToUserFolder(
                        $sourcePost->workspace,
                        $imageBytes,
                        'king-coffee-ai-'.uniqid().'.jpg',
                        $campaign->creator
                    );
                    $generatedImages[] = $savedMedia;
                }
            }
            if ($generatedImages !== []) {
                $media = $generatedImages; // ONLY USE AI GENERATED IMAGES
            }
        }

        $scheduledAt = $campaign->start_at->copy()->addDays($campaign->generated_posts * $campaign->interval_days);
        $platforms = $targetAccounts->map(fn ($account): array => [
            'social_account_id' => $account->id,
            'content_type' => ContentType::defaultFor($account->platform)->value,
        ])->all();

        $post = CreatePost::execute($sourcePost->workspace, $campaign->creator, [
            'content' => $content,
            'media' => $media,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'created_via' => CreatedVia::Automation,
            'content_workflow_id' => $campaign->content_workflow_id,
            'content_clone_campaign_id' => $campaign->id,
            'platforms' => $platforms,
        ]);

        $post->update([
            'workflow_status' => $campaign->require_approval ? 'pending_review' : 'approved',
            'status' => $campaign->require_approval ? PostStatus::Draft : PostStatus::Scheduled,
        ]);

        return $post;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveMedia(ContentCloneCampaign $campaign, Post $sourcePost): array
    {
        if ($campaign->generated_posts === 0 && filled($campaign->initial_media)) {
            return $campaign->initial_media;
        }

        return $sourcePost->media ?? [];
    }

    private function resolveContent(ContentCloneCampaign $campaign, Post $sourcePost, string $platform): string
    {
        $initialContent = trim((string) $campaign->initial_content);

        if ($campaign->generated_posts === 0 && $initialContent !== '') {
            return $initialContent;
        }

        return $this->generator->generate(
            workspace: $sourcePost->workspace,
            sourcePost: $sourcePost,
            theme: $campaign->theme,
            prompt: $campaign->prompt,
            platform: $platform,
            aiContentMode: $campaign->ai_content_mode ?? 'text_image',
        );
    }

    private function markCompleted(ContentCloneCampaign $campaign): void
    {
        $generatedPosts = $campaign->generated_posts + 1;
        $campaign->update([
            'generated_posts' => $generatedPosts,
            'next_run_at' => $campaign->start_at->copy()->addDays($generatedPosts * $campaign->interval_days),
            'last_error' => null,
            'is_active' => $generatedPosts < $campaign->total_posts,
        ]);
    }
}
