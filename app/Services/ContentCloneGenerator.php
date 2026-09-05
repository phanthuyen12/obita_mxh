<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\VideoScriptGenerator;
use App\Enums\Folder\Type as FolderType;
use App\Enums\Media\Type as MediaType;
use App\Enums\Workspace\ImageStyle;
use App\Models\Folder;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\Dify\DifyWorkflowClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentCloneGenerator
{
    /**
     * @return array<int, array{content: string, media: array<int, array<string, mixed>>, provider: string, video_scenes?: array<int, mixed>, ai_images_failed?: bool}>
     */
    public function previewSuggestions(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        ?string $imagePrompt,
        string $platform,
        int $aiImageCount = 0,
        ?string $aiImageStyle = null,
        ?string $aiLogoPath = null,
        bool $diffContentPerPage = false,
        ?string $aiImageResolution = null,
        ?string $aiImageAspectRatio = null,
        ?string $aiContentMode = 'text_image',
        ?array $videoScenes = null,
        ?string $videoHook = null,
        ?int $videoTargetDuration = null,
        ?string $characterName = null,
        ?string $characterDna = null,
        ?string $characterAvatar = null
    ): array {
        $sourceMedia = $sourcePost->media ?? [];

        if (config('content_clone.ai_provider') !== 'dify') {
            $generated = $this->generateStructured(
                workspace: $workspace,
                sourcePost: $sourcePost,
                theme: $theme,
                prompt: $prompt,
                platform: $platform,
                aiContentMode: $aiContentMode,
            );
            $content = $generated['content'];
            $keywords = $generated['image_keywords'];
            $imageTitle = $generated['image_title'] ?? '';
            $imageBody = $generated['image_body'] ?? '';

            $media = $sourceMedia;
            $aiImagesFailed = false;
            if ($aiContentMode === 'text_image' && $aiImageCount > 0 && ! empty($keywords)) {
                $generatedImages = [];
                $aiImage = app(AiImageClient::class);

                $finalImagePrompt = $this->buildFinalImagePrompt($workspace, $imagePrompt, $theme, $imageTitle, $imageBody);
                $orientation = $this->resolveOrientation($aiImageAspectRatio, $platform);

                $styleVal = $aiImageStyle ?? $workspace->image_style;
                $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);

                for ($i = 0; $i < $aiImageCount; $i++) {
                    $imageResult = $aiImage->generate(
                        keywords: $keywords,
                        style: $style,
                        orientation: $orientation,
                        language: $workspace->content_language ?? 'en',
                        customPrompt: $finalImagePrompt,
                        logoPath: $aiLogoPath ?: null,
                        customResolution: $aiImageResolution ?: null,
                        customAspectRatio: $aiImageAspectRatio ?: null,
                    );

                    if ($imageResult !== null) {
                        $imageBytes = $imageResult['bytes'];
                        if (filled($aiLogoPath)) {
                            $imageBytes = $this->overlayLogo($imageBytes, $aiLogoPath);
                        }

                        $savedMedia = $this->saveMediaToUserFolder(
                            $workspace,
                            $imageBytes,
                            'king-coffee-ai-'.uniqid().'.jpg'
                        );
                        $generatedImages[] = $savedMedia;
                    }
                }
                if ($generatedImages !== []) {
                    $media = $generatedImages; // DO NOT MERGE WITH ORIGINAL MEDIA
                } else {
                    $aiImagesFailed = true;
                }
            }

            $resolvedVideoScenes = $videoScenes;
            if ($aiContentMode === 'video_ai') {
                if (blank($resolvedVideoScenes)) {
                    $resolvedVideoScenes = $this->generateVideoScenesWithAi($workspace, $sourcePost, $theme, $prompt, $videoHook, $videoTargetDuration, $characterName, $characterDna, $characterAvatar);
                }

                // If user requested AI keyframes and video scenes have no start image, generate keyframes
                if ($aiImageCount > 0 && filled($resolvedVideoScenes)) {
                    $this->populateSceneKeyframes(
                        $resolvedVideoScenes,
                        $workspace,
                        $aiImageCount,
                        $aiImageStyle,
                        $aiImageAspectRatio,
                        $platform,
                        $prompt,
                        $theme,
                        $aiLogoPath,
                        $aiImageResolution
                    );
                }
            }

            $result = [
                'content' => $content,
                'media' => $media,
                'provider' => 'default',
                'ai_images_failed' => $aiImagesFailed,
            ];

            if ($aiContentMode === 'video_ai' && filled($resolvedVideoScenes)) {
                $result['video_scenes'] = $resolvedVideoScenes;
            }

            return [$result];
        }

        $outputs = null;
        try {
            $outputs = $this->runDify($workspace, $sourcePost, $theme, $prompt, $platform, $aiContentMode, $aiImageCount, 'post');
        } catch (\Throwable $e) {
            Log::warning('Dify workflow request failed, falling back to built-in generator: '.$e->getMessage());
        }

        if ($outputs === null) {
            $generated = $this->generateStructured(
                workspace: $workspace,
                sourcePost: $sourcePost,
                theme: $theme,
                prompt: $prompt,
                platform: $platform,
                aiContentMode: $aiContentMode,
            );
            $content = $generated['content'];
            $keywords = $generated['image_keywords'];
            $imageTitle = $generated['image_title'] ?? '';
            $imageBody = $generated['image_body'] ?? '';

            $media = $sourceMedia;
            $aiImagesFailed = false;
            if ($aiContentMode === 'text_image' && $aiImageCount > 0 && ! empty($keywords)) {
                $generatedImages = [];
                $aiImage = app(AiImageClient::class);

                $finalImagePrompt = $this->buildFinalImagePrompt($workspace, $imagePrompt, $theme, $imageTitle, $imageBody);
                $orientation = $this->resolveOrientation($aiImageAspectRatio, $platform);

                $styleVal = $aiImageStyle ?? $workspace->image_style;
                $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);

                for ($i = 0; $i < $aiImageCount; $i++) {
                    $imageResult = $aiImage->generate(
                        keywords: $keywords,
                        style: $style,
                        orientation: $orientation,
                        language: $workspace->content_language ?? 'en',
                        customPrompt: $finalImagePrompt,
                        logoPath: $aiLogoPath ?: null,
                        customResolution: $aiImageResolution ?: null,
                        customAspectRatio: $aiImageAspectRatio ?: null,
                    );

                    if ($imageResult !== null) {
                        $imageBytes = $imageResult['bytes'];
                        if (filled($aiLogoPath)) {
                            $imageBytes = $this->overlayLogo($imageBytes, $aiLogoPath);
                        }

                        $savedMedia = $this->saveMediaToUserFolder(
                            $workspace,
                            $imageBytes,
                            'king-coffee-ai-'.uniqid().'.jpg'
                        );
                        $generatedImages[] = $savedMedia;
                    }
                }
                if ($generatedImages !== []) {
                    $media = $generatedImages;
                } else {
                    $aiImagesFailed = true;
                }
            }

            $resolvedVideoScenes = $videoScenes;
            if ($aiContentMode === 'video_ai' && blank($resolvedVideoScenes)) {
                $resolvedVideoScenes = $this->generateVideoScenesWithAi($workspace, $sourcePost, $theme, $prompt, $videoHook, $videoTargetDuration, $characterName, $characterDna, $characterAvatar);
            }

            $result = [
                'content' => $content,
                'media' => $media,
                'provider' => 'default_fallback',
                'ai_images_failed' => $aiImagesFailed,
            ];

            if ($aiContentMode === 'video_ai' && filled($resolvedVideoScenes)) {
                $result['video_scenes'] = $resolvedVideoScenes;
            }

            return [$result];
        }

        $resolvedVideoScenes = $videoScenes;
        if ($aiContentMode === 'video_ai' && blank($resolvedVideoScenes)) {
            $resolvedVideoScenes = $this->generateVideoScenesWithAi($workspace, $sourcePost, $theme, $prompt, $videoHook, $videoTargetDuration, $characterName, $characterDna, $characterAvatar);
        }

        $outputMedia = $this->mediaFromValue($outputs);

        // If aiImageCount > 0 and outputMedia does not contain new AI images, generate via Dify image mode or fallback
        if ($aiContentMode === 'text_image' && $aiImageCount > 0) {
            $hasAiImage = collect($outputMedia)->contains(fn ($m) => data_get($m, 'source') === 'ai' || str_contains((string) data_get($m, 'url', ''), 'files/tools'));

            if (! $hasAiImage) {
                // 1. Try Dify with content_type: 'image'
                try {
                    $imageOutputs = $this->runDify($workspace, $sourcePost, $theme, $prompt, $platform, 'text_image', $aiImageCount, 'image');
                    $difyImages = $this->mediaFromValue($imageOutputs);
                    if (! empty($difyImages)) {
                        $outputMedia = $difyImages;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Dify image workflow failed: '.$e->getMessage());
                }

                // 2. Fallback to AiImageClient if Dify returned no images
                $hasAiImageNow = collect($outputMedia)->contains(fn ($m) => data_get($m, 'source') === 'ai' || str_contains((string) data_get($m, 'url', ''), 'files/tools'));
                if (! $hasAiImageNow) {
                    $aiImage = app(AiImageClient::class);
                    $finalImagePrompt = $this->buildFinalImagePrompt($workspace, $imagePrompt ?: $prompt, $theme, null, null);
                    $orientation = $this->resolveOrientation($aiImageAspectRatio, $platform);
                    $styleVal = $aiImageStyle ?? $workspace->image_style;
                    $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);
                    $generatedImages = [];

                    for ($i = 0; $i < $aiImageCount; $i++) {
                        $imageResult = $aiImage->generate(
                            keywords: [$theme ?? 'King Coffee'],
                            style: $style,
                            orientation: $orientation,
                            language: $workspace->content_language ?? 'en',
                            customPrompt: $finalImagePrompt,
                            logoPath: $aiLogoPath ?: null,
                            customResolution: $aiImageResolution ?: null,
                            customAspectRatio: $aiImageAspectRatio ?: null,
                        );

                        if ($imageResult !== null) {
                            $imageBytes = $imageResult['bytes'];
                            if (filled($aiLogoPath)) {
                                $imageBytes = $this->overlayLogo($imageBytes, $aiLogoPath);
                            }

                            $savedMedia = $this->saveMediaToUserFolder(
                                $workspace,
                                $imageBytes,
                                'king-coffee-ai-'.uniqid().'.jpg'
                            );
                            $generatedImages[] = $savedMedia;
                        }
                    }

                    if (! empty($generatedImages)) {
                        $outputMedia = $generatedImages;
                    }
                }
            }
        }

        // Save any remote Dify images into user's media library folder as well
        if (! empty($outputMedia)) {
            $outputMedia = collect($outputMedia)->map(function ($item) use ($workspace) {
                if (data_get($item, 'source') === 'ai' || str_contains((string) data_get($item, 'url', ''), 'files/tools')) {
                    return $this->saveMediaToUserFolder($workspace, $item, data_get($item, 'original_filename', 'king-coffee-ai-'.uniqid().'.jpg'));
                }

                return $item;
            })->all();
        }

        $outputMedia = ! empty($outputMedia) ? $outputMedia : $sourceMedia;
        $suggestions = data_get($outputs, 'suggestions');

        if (is_array($suggestions)) {
            return collect($suggestions)
                ->map(function (mixed $suggestion) use ($outputMedia, $aiContentMode, $resolvedVideoScenes): array {
                    $rawText = trim((string) data_get($suggestion, 'content', data_get($suggestion, 'answer', $suggestion)));
                    $item = [
                        'content' => $this->cleanFacebookPostContent($rawText),
                        'media' => $this->mediaFromValue($suggestion) ?: $outputMedia,
                        'provider' => 'dify',
                    ];

                    if ($aiContentMode === 'video_ai' && filled($resolvedVideoScenes)) {
                        $item['video_scenes'] = $resolvedVideoScenes;
                    }

                    return $item;
                })
                ->filter(fn (array $suggestion): bool => $suggestion['content'] !== '')
                ->values()
                ->all();
        }

        $result = [
            'content' => $this->contentFromOutputs($outputs, $sourcePost->content),
            'media' => $outputMedia,
            'provider' => 'dify',
        ];

        if ($aiContentMode === 'video_ai' && filled($resolvedVideoScenes)) {
            $result['video_scenes'] = $resolvedVideoScenes;
        }

        return [$result];
    }

    /**
     * @return array<int, string>
     */
    public function suggestions(Workspace $workspace, Post $sourcePost, ?string $theme, ?string $prompt, string $platform): array
    {
        return collect($this->previewSuggestions($workspace, $sourcePost, $theme, $prompt, null, $platform, 0, null))
            ->pluck('content')
            ->all();
    }

    public function generate(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        string $platform,
        ?string $aiContentMode = 'text_image'
    ): string {
        return $this->generateStructured($workspace, $sourcePost, $theme, $prompt, $platform, $aiContentMode)['content'];
    }

    /**
     * @return array{content: string, image_keywords: array<int, string>, image_title?: string, image_body?: string}
     */
    public function generateStructured(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        string $platform,
        ?string $aiContentMode = 'text_image'
    ): array {
        if (config('content_clone.ai_provider') === 'dify') {
            try {
                $outputs = $this->runDify($workspace, $sourcePost, $theme, $prompt, $platform, $aiContentMode, 0, 'post');

                return [
                    'content' => $this->contentFromOutputs($outputs, $sourcePost->content),
                    'image_title' => $theme ?? '',
                    'image_body' => $prompt ?? '',
                    'image_keywords' => array_filter([$theme, $prompt]),
                ];
            } catch (\Throwable $e) {
                Log::warning('Dify workflow request in generateStructured failed, falling back to built-in generator: '.$e->getMessage());
            }
        }

        $instructions = $aiContentMode === 'video_ai'
            ? $this->videoInstructions($workspace, $sourcePost, $theme, $prompt, $platform)
            : $this->instructions($workspace, $sourcePost, $theme, $prompt, $platform);

        $response = (new PostContentGenerator(
            workspace: $workspace,
            currentContent: $sourcePost->content,
            platformContext: $platform,
        ))->prompt($instructions);

        $structured = $response->structured ?? [];

        return [
            'content' => trim((string) data_get($structured, 'content', $sourcePost->content)),
            'image_title' => (string) data_get($structured, 'image_title', ''),
            'image_body' => (string) data_get($structured, 'image_body', ''),
            'image_keywords' => (array) data_get($structured, 'image_keywords', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runDify(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        string $platform,
        ?string $aiContentMode = 'text_image',
        int $aiImageCount = 0,
        ?string $overrideContentType = null
    ): array {
        // In ContentCloneGenerator, we only generate copy ('post') or images ('image').
        // Video rendering via RevidAPI is only invoked when rendering scene videos.
        $contentType = $overrideContentType ?? 'post';

        $instructions = $aiContentMode === 'video_ai'
            ? $this->videoInstructions($workspace, $sourcePost, $theme, $prompt, $platform)
            : $this->instructions($workspace, $sourcePost, $theme, $prompt, $platform);

        $productFiles = collect($sourcePost->media ?? [])
            ->filter(fn ($m) => data_get($m, 'type', 'image') === 'image' && filled(data_get($m, 'url')))
            ->map(fn ($m) => [
                'type' => 'image',
                'transfer_method' => 'remote_url',
                'url' => data_get($m, 'url'),
            ])
            ->values()
            ->all();

        $firstImageUrl = collect($sourcePost->media ?? [])
            ->filter(fn ($m) => data_get($m, 'type', 'image') === 'image' && filled(data_get($m, 'url')))
            ->map(fn ($m) => data_get($m, 'url'))
            ->first() ?? '';

        $requirement = collect([
            $prompt ? "Yêu cầu: {$prompt}" : null,
            $theme ? "Chủ đề: {$theme}" : null,
            "Nền tảng: {$platform}",
            "Nội dung bài viết nguồn: {$sourcePost->content}",
        ])->filter()->implode("\n\n");

        $inputs = [
            'content_type' => $contentType,
            'requirement' => $requirement,
            'source_content' => $sourcePost->content,
            'theme' => $theme ?? '',
            'prompt' => $prompt ?? '',
            'platform' => $platform,
            'brand_name' => $workspace->name ?? 'King Coffee',
            'brand_description' => $workspace->brand_description ?? '',
            'brand_voice_traits' => json_encode($workspace->brand_voice_traits ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_language' => is_object($workspace->content_language)
                ? $workspace->content_language->value
                : (string) ($workspace->content_language ?? 'vi'),
            'instructions' => $instructions,
            'imageProduct' => (string) $firstImageUrl,
            'image_url' => (string) $firstImageUrl,
            'aspect_ratio' => '9:16',
        ];

        if (! empty($productFiles)) {
            $inputs['Product'] = $productFiles;
        }

        return app(DifyWorkflowClient::class)->run($inputs, "workspace:{$workspace->id}", (string) config('services.dify.content_clone_api_key'));
    }

    private function instructions(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        string $platform
    ): string {
        $lang = is_object($workspace->content_language)
            ? $workspace->content_language->value
            : (string) ($workspace->content_language ?? 'vi');

        return collect([
            'Bạn là một Giám Đốc Sáng Tạo Nội Dung & Chuyên Gia Copywriting hàng đầu cho thương hiệu King Coffee.',
            'NHIỆM VỤ: Dựa trên bài viết nguồn và yêu cầu được cung cấp, tạo một bài viết mạng xã hội (Facebook/Instagram/LinkedIn) hoàn chỉnh, cuốn hút, đúng tone & voice sang trọng, đẳng cấp của King Coffee, chuẩn SEO và có tính lan truyền (viral) cao.',
            'QUY TẮC QUAN TRỌNG VỀ ĐỊNH DẠNG ĐẦU RA:',
            '- CHỈ TRẢ VỀ NỘI DUNG BÀI ĐĂNG THỰC TẾ để đăng ngay lên mạng xã hội.',
            '- TUYỆT ĐỐI KHÔNG thêm bất kỳ câu chào hỏi nào (như "Tuyệt vời!", "Chào bạn", "Dưới đây là bài viết...").',
            '- TUYỆT ĐỐI KHÔNG in các nhãn cấu trúc kỹ thuật như "### Bài Viết Mạng Xã Hội", "**1. 🔥 TIÊU ĐỀ (HEADLINE):**", "**2. 📝 NỘI DUNG CHÍNH (BODY):**", "**3. 🎯 LỜI KÊU GỌI HÀNH ĐỘNG (CTA):**", "**4. 🏷️ BỘ HASHTAGS THỊNH HÀNH:**", hay "**5. 🎨 GỢI Ý HÌNH ẢNH...**".',
            '- Bắt đầu trực tiếp bằng TIÊU ĐỀ BÀI VIẾT (in đậm, kèm emoji nổi bật), tiếp theo là thân bài hấp dẫn theo công thức AIDA (chia đoạn ngắn, bullet points súc tích), ưu đãi hấp dẫn, lời kêu gọi hành động (CTA) tự nhiên và kết thúc bằng bộ hashtags thịnh hành.',
            '- Ngôn ngữ: '.$lang,
            '- Tối ưu cho nền tảng: '.$platform,
            '- Giữ nguyên giá trị cốt lõi, sáng tạo mới mẻ, không sao chép thô từ bài gốc.',
            $theme ? "Chủ đề chiến dịch: {$theme}" : 'Chủ đề chiến dịch: tôn vinh hương vị và giá trị thương hiệu King Coffee.',
            $prompt ? "Yêu cầu bổ sung: {$prompt}" : null,
            "Bài viết nguồn:\n{$sourcePost->content}",
        ])->filter()->implode("\n\n");
    }

    private function videoInstructions(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        string $platform
    ): string {
        return collect([
            'Bạn là Đạo Diễn & Biên Kịch Video Quảng Cáo AI cho thương hiệu King Coffee.',
            'NHIỆM VỤ: Xây dựng kịch bản phân cảnh video thương mại ngắn (15s - 30s) chuyên nghiệp, ấn tượng bám sát nội dung bài nguồn và yêu cầu người dùng.',
            'CẤU TRÚC KỊCH BẢN:',
            '1. Tổng quan: Ý tưởng chính, thời lượng, phong cách âm nhạc/BGM, tone & mood sang trọng đẳng cấp King Coffee.',
            '2. Phân cảnh chi tiết từng Scene (Scene 1 đến Scene 4):',
            '   - Bối cảnh & Hành động nhân vật / sản phẩm King Coffee.',
            '   - Chuyển động máy quay (Camera Movement: Slow-motion, Close-up, Dolly Zoom, Drone shot).',
            '   - Voiceover (Lời thuyết minh tiếng Việt truyền cảm) & Text hiển thị trên màn hình.',
            '3. PROMPT VIDEO AI (Veo 3.1 / Gen-3):',
            '   PROMPT: [Mô tả chi tiết bằng tiếng Anh phân cảnh chính ấn tượng nhất của King Coffee để đưa vào công cụ tạo video AI]',
            $theme ? "Chủ đề chiến dịch: {$theme}" : null,
            $prompt ? "Yêu cầu thêm: {$prompt}" : null,
            "Nội dung nguồn:\n{$sourcePost->content}",
        ])->filter()->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $outputs
     */
    private function contentFromOutputs(array $outputs, string $fallback): string
    {
        $content = trim((string) data_get($outputs, 'content', data_get($outputs, 'video_script', data_get($outputs, 'answer', data_get($outputs, 'text', '')))));

        if ($content !== '') {
            return $this->cleanFacebookPostContent($content);
        }

        $firstSuggestion = data_get($outputs, 'suggestions.0');
        $suggestedContent = trim((string) data_get($firstSuggestion, 'content', $firstSuggestion));

        if ($suggestedContent !== '') {
            return $this->cleanFacebookPostContent($suggestedContent);
        }

        return $fallback;
    }

    /**
     * Clean raw Dify/LLM output into a polished, natural, publish-ready Facebook marketing post.
     */
    public function cleanFacebookPostContent(string $text): string
    {
        // 1. Strip markdown image embeds (e.g. ![output0.png](...))
        $text = (string) preg_replace('/!\[.*?\]\(https?:\/\/[^\s\)]+\)\s*/i', '', $text);

        // 2. Cut off trailing visual design instructions / image prompt notes (e.g. "🎨 GỢI Ý HÌNH ẢNH...", "TÊN FILE HÌNH ẢNH:", etc.)
        if (preg_match('/(?:\n|\A)\s*(?:(?:\*{1,2}|#{1,6})?\s*(?:\d+\.\s*)?(?:🎨|📸|🖼️)?\s*(?:\*{1,2}|#{1,6})?\s*(?:GỢI Ý HÌNH ẢNH|VISUAL MINH HỌA|ẢNH QUẢNG CÁO|PROMPT CHÍNH|TÊN FILE HÌNH ẢNH|HÌNH ẢNH MINH HỌA)|🎨\s*\**\s*GỢI Ý)/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $matches[0][1]);
        }

        // 3. Remove header banners and conversation chatter
        // E.g. "**BÀI VIẾT MẠNG XÃ HỘI (FACEBOOK)**", "### Bài Viết Mạng Xã Hội", "☕ **BÀI VIẾT NỘI DUNG SÁNG TẠO - KING COFFEE**"
        $text = (string) preg_replace('/(?:\n|\A)\s*(?:\*{1,2}|#{1,6})?\s*(?:☕\s*)?BÀI VIẾT(?:\s*NỘI DUNG SÁNG TẠO)?(?:\s*MẠNG XÃ HỘI)?(?:\s*\([^)]*\))?(?:\s*-\s*KING COFFEE)?\s*(?:\*{1,2})?\s*(?:\n|\Z)/iu', "\n", $text);
        $text = (string) preg_replace('/(?:\n|\A)\s*Tuyệt vời![\s\S]*?(?:\n---\n|\n\n)/iu', "\n", $text);

        // 4. Strip markdown dividers: --- or ___ or ***
        $text = (string) preg_replace('/(?:\n|\A)\s*[-_*]{3,}\s*(?:\n|\Z)/u', "\n\n", $text);

        // 5. Strip all technical section headings and number labels:
        // Headline: 🔥 **TIÊU ĐỀ:** or **1. 🔥 TIÊU ĐỀ (HEADLINE):** or TIÊU ĐỀ:
        $text = (string) preg_replace('/(?:\n|\A)\s*(?:(?:\d+\.\s*)?(?:🔥|⚡|⭐|🌟)\s*)?(?:\*{1,2}|#{1,6})?\s*(?:\d+\.\s*)?(?:🔥|⚡|⭐|🌟)?\s*(?:\*{1,2})?\s*TIÊU ĐỀ(?:\s*\([^)]*\))?\s*:?\s*(?:\*{1,2})?:?\s*/iu', "\n\n", $text);

        // Body: 📝 **NỘI DUNG CHÍNH:** or **2. 📝 NỘI DUNG CHÍNH (BODY):**
        $text = (string) preg_replace('/(?:\n|\A)\s*(?:(?:\d+\.\s*)?(?:📝|📄|✍️)\s*)?(?:\*{1,2}|#{1,6})?\s*(?:\d+\.\s*)?(?:📝|📄|✍️)?\s*(?:\*{1,2})?\s*(?:NỘI DUNG CHÍNH|NỘI DUNG|THÂN BÀI)(?:\s*\([^)]*\))?\s*:?\s*(?:\*{1,2})?:?\s*/iu', "\n\n", $text);

        // CTA: 🎯 **LỜI KÊU GỌI HÀNH ĐỘNG (CTA):** or 🎯 LỜI KÊU GỌI HÀNH ĐỘNG:
        $text = (string) preg_replace('/(?:\n|\A)\s*(?:(?:\d+\.\s*)?(?:🎯|👉|🛒|📞)\s*)?(?:\*{1,2}|#{1,6})?\s*(?:\d+\.\s*)?(?:🎯|👉|🛒|📞)?\s*(?:\*{1,2})?\s*(?:LỜI KÊU GỌI HÀNH ĐỘNG|KÊU GỌI HÀNH ĐỘNG|CTA)(?:\s*\([^)]*\))?\s*:?\s*(?:\*{1,2})?:?\s*/iu', "\n\n", $text);

        // Hashtags: 🏷️ **BỘ HASHTAGS THỊNH HÀNH:** or 🏷️ HASHTAGS:
        $text = (string) preg_replace('/(?:\n|\A)\s*(?:(?:\d+\.\s*)?(?:🏷️|🔖|#)\s*)?(?:\*{1,2}|#{1,6})?\s*(?:\d+\.\s*)?(?:🏷️|🔖|#)?\s*(?:\*{1,2})?\s*(?:BỘ HASHTAGS THỊNH HÀNH|BỘ HASHTAGS|HASHTAGS?|TAGS?)(?:\s*\([^)]*\))?\s*:?\s*(?:\*{1,2})?:?\s*/iu', "\n\n", $text);

        // 6. Clean up multiple excessive consecutive blank lines
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mediaFromValue(mixed $value): array
    {
        $media = data_get($value, 'files', data_get($value, 'video_files', data_get($value, 'media', data_get($value, 'images', []))));

        if (blank($media)) {
            $media = data_get($value, 'image_urls', data_get($value, 'image_url', []));
        }

        // Also check if text contains markdown image or video URLs
        $text = (string) data_get($value, 'answer', data_get($value, 'content', ''));
        $extractedUrls = [];
        if ($text !== '') {
            if (preg_match_all('/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i', $text, $matches)) {
                $extractedUrls = array_merge($extractedUrls, $matches[1]);
            }
            if (preg_match_all('/(https?:\/\/[^\s\)]+\.(?:mp4|webm|mov)(?:\?[^\s\)]*)?)/i', $text, $matches)) {
                foreach ($matches[1] as $videoUrl) {
                    $extractedUrls[] = [
                        'url' => $videoUrl,
                        'type' => 'video',
                    ];
                }
            }
        }

        $allMedia = collect(is_array($media) ? $media : [$media])
            ->merge($extractedUrls)
            ->map(fn (mixed $item): ?array => $this->normalizeMediaItem($item))
            ->filter()
            ->unique(fn (array $item) => $item['url'])
            ->values()
            ->all();

        return $allMedia;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMediaItem(mixed $item): ?array
    {
        if (is_string($item)) {
            $url = trim($item);
            if ($url === '') {
                return null;
            }

            $isVideo = (bool) preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url);

            return [
                'id' => md5($url),
                'url' => $url,
                'type' => $isVideo ? 'video' : 'image',
                'source' => 'ai',
            ];
        }

        if (! is_array($item)) {
            return null;
        }

        $url = trim((string) data_get($item, 'url', data_get($item, 'image_url', data_get($item, 'path', ''))));

        if ($url === '') {
            return null;
        }

        $isVideo = (bool) preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url) || data_get($item, 'type') === 'video';

        return [
            'id' => (string) data_get($item, 'id', md5($url)),
            'url' => $url,
            'path' => data_get($item, 'path'),
            'type' => $isVideo ? 'video' : (string) data_get($item, 'type', 'image'),
            'mime_type' => data_get($item, 'mime_type', $isVideo ? 'video/mp4' : 'image/jpeg'),
            'original_filename' => data_get($item, 'original_filename', basename(parse_url($url, PHP_URL_PATH) ?: ($isVideo ? 'ai-video.mp4' : 'ai-image.jpg'))),
            'source' => (string) data_get($item, 'source', 'ai'),
            'source_meta' => (array) data_get($item, 'source_meta', []),
            'meta' => (array) data_get($item, 'meta', []),
        ];
    }

    /**
     * Overlay a transparent logo PNG onto the generated AI image.
     */
    public function overlayLogo(string $imageBytes, string $logoPath): string
    {
        Log::info('overlayLogo called', ['logoPath' => $logoPath, 'imageBytesLen' => strlen($imageBytes)]);

        $image = @imagecreatefromstring($imageBytes);
        if (! $image) {
            Log::warning('overlayLogo: failed to create GD image from AI bytes');

            return $imageBytes;
        }

        $logoBytes = null;
        if (Storage::disk('public')->exists($logoPath)) {
            $logoBytes = Storage::disk('public')->get($logoPath);
            Log::info('overlayLogo: logo found on public disk', ['size' => strlen($logoBytes)]);
        } elseif (Storage::exists($logoPath)) {
            $logoBytes = Storage::get($logoPath);
            Log::info('overlayLogo: logo found on default disk', ['size' => strlen($logoBytes)]);
        }

        if (! $logoBytes) {
            Log::warning('overlayLogo: logo file not found on any disk', ['logoPath' => $logoPath]);
            imagedestroy($image);

            return $imageBytes;
        }

        $logo = @imagecreatefromstring($logoBytes);
        if (! $logo) {
            imagedestroy($image);

            return $imageBytes;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);

        $maxLogoWidth = (int) ($imageWidth * 0.15);
        if ($logoWidth > $maxLogoWidth) {
            $ratio = $maxLogoWidth / $logoWidth;
            $newLogoWidth = $maxLogoWidth;
            $newLogoHeight = (int) ($logoHeight * $ratio);

            $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);

            $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
            imagefill($resizedLogo, 0, 0, $transparent);

            imagecopyresampled($resizedLogo, $logo, 0, 0, 0, 0, $newLogoWidth, $newLogoHeight, $logoWidth, $logoHeight);
            imagedestroy($logo);
            $logo = $resizedLogo;
            $logoWidth = $newLogoWidth;
            $logoHeight = $newLogoHeight;
        }

        $margin = 20;
        $destX = $imageWidth - $logoWidth - $margin;
        $destY = $imageHeight - $logoHeight - $margin;

        imagecopy($image, $logo, $destX, $destY, 0, 0, $logoWidth, $logoHeight);

        ob_start();
        imagejpeg($image, null, 95);
        $outputBytes = ob_get_clean();

        imagedestroy($image);
        imagedestroy($logo);

        return $outputBytes ?: $imageBytes;
    }

    /**
     * Save generated AI image into the user's personal email folder in the Media Library.
     *
     * @param  array<string, mixed>|string  $mediaItemOrBytes
     * @return array<string, mixed>
     */
    public function saveMediaToUserFolder(
        Workspace $workspace,
        array|string $mediaItemOrBytes,
        ?string $originalFilename = null,
        ?User $user = null
    ): array {
        $user = $user ?? auth()->user() ?? $workspace->owner ?? $workspace->users()->first();
        $userEmail = $user?->email ?? ($workspace->owner?->email ?? 'ai-creatives@kingcoffee.com');

        // 1. Get or create folder by user email
        $folder = Folder::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', $userEmail)
            ->first();

        if (! $folder) {
            $folder = Folder::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $userEmail,
                'type' => FolderType::Personal,
                'created_by' => $user?->id ?? $workspace->user_id,
                'owner_user_id' => $user?->id ?? $workspace->user_id,
                'is_locked' => false,
                'is_shared_with_workspace' => true,
            ]);
        }

        $filename = $originalFilename ?? ('king-coffee-'.uniqid().'.jpg');
        $filePath = null;
        $fileSize = 0;
        $mimeType = 'image/jpeg';
        $mediaType = MediaType::Image;

        if (is_array($mediaItemOrBytes)) {
            $url = (string) data_get($mediaItemOrBytes, 'url', '');
            $filePath = data_get($mediaItemOrBytes, 'path');

            if (empty($filePath) && filled($url)) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    try {
                        $resp = Http::timeout(30)->get($url);
                        if ($resp->successful()) {
                            $bytes = $resp->body();
                            $savedName = 'medias/'.Str::uuid().'.jpg';
                            Storage::put($savedName, $bytes);
                            $filePath = $savedName;
                            $fileSize = strlen($bytes);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to download Dify media to local library: '.$e->getMessage());
                    }
                }
            }
        } elseif (is_string($mediaItemOrBytes)) {
            if (filter_var($mediaItemOrBytes, FILTER_VALIDATE_URL)) {
                try {
                    $resp = Http::timeout(30)->get($mediaItemOrBytes);
                    if ($resp->successful()) {
                        $bytes = $resp->body();
                        $savedName = 'medias/'.Str::uuid().'.jpg';
                        Storage::put($savedName, $bytes);
                        $filePath = $savedName;
                        $fileSize = strlen($bytes);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to download media URL: '.$e->getMessage());
                }
            } else {
                // Binary bytes
                $bytes = $mediaItemOrBytes;
                $savedName = 'medias/'.Str::uuid().'.jpg';
                Storage::put($savedName, $bytes);
                $filePath = $savedName;
                $fileSize = strlen($bytes);
            }
        }

        if (empty($filePath)) {
            $filePath = 'medias/'.Str::uuid().'.jpg';
        }

        if ($fileSize === 0 && Storage::exists($filePath)) {
            $fileSize = (int) Storage::size($filePath);
        }

        // 2. Create Media record in the workspace and folder
        $mediaRecord = $workspace->media()->create([
            'group_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'folder_id' => $folder->id,
            'uploaded_by' => $user?->id ?? $workspace->user_id,
            'collection' => 'assets',
            'type' => $mediaType,
            'disk' => config('filesystems.default', 'public'),
            'path' => $filePath,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $fileSize,
        ]);

        return [
            'id' => $mediaRecord->id,
            'url' => Storage::url($filePath),
            'path' => $filePath,
            'type' => 'image',
            'source' => 'ai',
            'mime_type' => $mimeType,
            'folder_id' => $folder->id,
            'original_filename' => $filename,
        ];
    }

    /**
     * Map aspect ratio or platform context into standard orientation for image generators.
     */
    public function resolveOrientation(?string $aspectRatio, string $platform = 'facebook'): string
    {
        if (filled($aspectRatio)) {
            return match ($aspectRatio) {
                '9:16', '4:5', '3:4', '2:3', '1:2' => 'portrait',
                '1:1', '4:4', 'square' => 'square',
                '16:9', '3:2', '21:9' => 'landscape',
                default => 'landscape',
            };
        }

        return match ($platform) {
            'tiktok', 'instagram-story', 'facebook-story' => 'portrait',
            'instagram' => 'square',
            default => 'landscape',
        };
    }

    /**
     * Build an ultra-realistic, rich, brand-tailored commercial photography prompt.
     */
    public function buildFinalImagePrompt(
        Workspace $workspace,
        ?string $customImagePrompt,
        ?string $theme,
        ?string $imageTitle,
        ?string $imageBody
    ): string {
        $brandName = $workspace->name ?: 'King Coffee';
        $brandDesc = $workspace->brand_description;

        if (filled($customImagePrompt)) {
            $prompt = $customImagePrompt;
            if (filled($imageTitle)) {
                $prompt .= " - Include concept: \"{$imageTitle}\"";
            }
            if (filled($imageBody)) {
                $prompt .= " - Subtitle detail: \"{$imageBody}\"";
            }

            return $prompt;
        }

        $parts = [
            "High-end commercial advertising product photography for {$brandName}.",
            $brandDesc ? "Brand Context: {$brandDesc}." : null,
            $theme ? "Campaign Theme: {$theme}." : null,
            $imageTitle ? "Key Concept: \"{$imageTitle}\"." : null,
            $imageBody ? "Subtitle/Detail: \"{$imageBody}\"." : null,
            'Composition: Luxury hero product presentation, rich coffee beans, steam rising, elegant studio lighting, golden rim accents, crisp depth of field, sharp focus, clean background aesthetic, professional color grading, ultra-realistic 8k UHD.',
        ];

        return implode(' ', array_filter($parts));
    }

    /**
     * Generate structured video scenes using AI agent (VideoScriptGenerator), falling back to defaults if needed.
     *
     * @return array<int, array{duration: int, context_prompt: string, action_prompt: string, start_image: string, end_image: string, video_url: string, transition: string, voiceover_text: string}>
     */
    public function generateVideoScenesWithAi(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme = null,
        ?string $prompt = null,
        ?string $videoHook = null,
        ?int $videoTargetDuration = 32,
        ?string $characterName = null,
        ?string $characterDna = null,
        ?string $characterAvatar = null
    ): array {
        $targetDuration = $videoTargetDuration ?: 32;
        $sceneCount = max(2, min(8, (int) round($targetDuration / 8)));
        $actualDuration = $sceneCount * 8;
        $startAvatar = filled($characterAvatar) ? trim($characterAvatar) : '';

        try {
            $agent = new VideoScriptGenerator(
                workspace: $workspace,
                productName: $workspace->name ?: 'King Coffee',
                productDescription: $sourcePost->content ?: $prompt,
                hook: $videoHook,
                targetDuration: $actualDuration,
                sceneCount: $sceneCount,
                characterName: $characterName,
                characterDna: $characterDna,
            );

            $instructions = "Hãy biên kịch chi tiết {$sceneCount} phân cảnh (mỗi cảnh đúng 8 giây, tổng cộng {$actualDuration}s) cho kịch bản video review sản phẩm với hook: \"{$videoHook}\".";
            $response = $agent->prompt($instructions);
            $scenes = (array) data_get($response->structured, 'scenes', []);

            if (count($scenes) >= $sceneCount) {
                return array_map(function ($scene) use ($startAvatar) {
                    return [
                        'duration' => (int) ($scene['duration'] ?? 8),
                        'context_prompt' => (string) ($scene['context_prompt'] ?? ''),
                        'action_prompt' => (string) ($scene['action_prompt'] ?? ''),
                        'start_image' => $startAvatar,
                        'end_image' => $startAvatar,
                        'video_url' => '',
                        'transition' => (string) ($scene['transition'] ?? 'glitch'),
                        'voiceover_text' => (string) ($scene['voiceover_text'] ?? ''),
                    ];
                }, array_slice($scenes, 0, $sceneCount));
            }
        } catch (\Throwable $e) {
            Log::warning('VideoScriptGenerator prompt failed, falling back to built-in templates: '.$e->getMessage());
        }

        return $this->buildDefaultVideoScenes(
            $workspace,
            $sourcePost,
            $theme,
            $prompt,
            $videoHook,
            $targetDuration,
            $characterName,
            $characterDna,
            $characterAvatar
        );
    }

    /**
     * Build structured default storyboard scenes matching King Coffee / brand commercial style.
     *
     * @return array<int, array{duration: int, context_prompt: string, action_prompt: string, start_image: string, end_image: string, video_url: string, transition: string, voiceover_text: string}>
     */
    public function buildDefaultVideoScenes(
        Workspace $workspace,
        Post $sourcePost,
        ?string $theme,
        ?string $prompt,
        ?string $videoHook = null,
        ?int $videoTargetDuration = 30,
        ?string $characterName = null,
        ?string $characterDna = null,
        ?string $characterAvatar = null
    ): array {
        $brandName = $workspace->name ?: 'King Coffee';
        $targetDuration = $videoTargetDuration ?: 30;
        $reviewer = filled($characterName) ? trim($characterName) : 'Reviewer King Coffee';
        $startAvatar = filled($characterAvatar) ? trim($characterAvatar) : '';

        // Expand videoHook into a natural spoken dialogue if it is short or a keyword/topic
        $hookVoiceover = match (true) {
            blank($videoHook) => "Xin chào các bạn! Hôm nay hãy cùng {$reviewer} khám phá và trải nghiệm ngay tuyệt phẩm cà phê {$brandName} đậm đà hảo hạng này nhé!",
            mb_strlen($videoHook) <= 25 || preg_match('/^(giới thiệu|review|mở đầu|hook|sản phẩm|cà phê)/iu', (string) $videoHook) => "Xin chào các bạn! Hôm nay {$reviewer} sẽ mang đến cho mọi người trải nghiệm thực tế về {$brandName} - tuyệt phẩm cà phê đậm đà mà bạn nhất định phải thử ngay!",
            default => trim((string) $videoHook),
        };

        $hookPrompt = "Cinematic 8K commercial video product review shot for {$brandName}. Reviewer {$reviewer} stands in a modern luxury cafe lounge with warm bokeh ambient lighting, holding the pristine original {$brandName} coffee product packaging with authentic golden crown logo and typography facing camera, looking directly at viewers with a bright confident smile.";

        $hookAction = "Fast dynamic push-in zoom with high-energy impact (0s-3s: camera zooms in smoothly on reviewer {$reviewer} smiling warmly while presenting the {$brandName} product to the viewer; 3s-6s: reviewer speaks expressively with synchronized lip-sync and gestures naturally towards the packaging details; 6s-8s: gentle orbital pan keeping the product packaging and logo 100% sharp and stable as the reviewer nods with genuine approval).";

        if ($targetDuration <= 16) {
            // 2 scenes ~ 16s (Reels / TikTok short format - 8s per scene)
            return [
                [
                    'duration' => 8,
                    'context_prompt' => $hookPrompt,
                    'action_prompt' => $hookAction,
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'glitch',
                    'voiceover_text' => $hookVoiceover,
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Cinematic 8K commercial video hero showcase: Reviewer {$reviewer} proudly holding {$brandName} packaging beside a gleaming luxury golden pedestal with subtle smoke and sparkling golden particles, royal crown emblem glowing with soft lens flare, shallow depth of field.",
                    'action_prompt' => "Majestic reveal shot (0s-3s: camera tilts up smoothly from the premium product to reviewer {$reviewer} smiling with pride; 3s-6s: reviewer speaks with passionate lip-sync emphasizing why everyone must experience this coffee; 6s-8s: subtle push-out ending on a crisp product badge while reviewer gives a confident thumbs up).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'fade_black',
                    'voiceover_text' => "{$brandName} - Tuyệt tác cà phê của Vua. Đánh giá 10 trên 10 cho chất lượng, các bạn hãy đặt mua ngay hôm nay để nhận ưu đãi đặc quyền nhé!",
                ],
            ];
        }

        if ($targetDuration <= 32) {
            // 4 scenes ~ 32s (Standard Viral Commercial Product Review - 8s per scene)
            return [
                [
                    'duration' => 8,
                    'context_prompt' => $hookPrompt,
                    'action_prompt' => $hookAction,
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'glitch',
                    'voiceover_text' => $hookVoiceover,
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Cinematic lifestyle review scene in a sunlit modern aesthetic cafe and workspace. Reviewer {$reviewer} sits at a polished wooden table with a fresh steaming cup of {$brandName} coffee and packaging displayed prominently, showcasing the rich aroma and deep dark color under soft morning sunlight, 8K commercial video realism.",
                    'action_prompt' => "Smooth tracking shot (0s-3s: camera tracks in as reviewer {$reviewer} reaches for the steaming {$brandName} coffee cup on the desk with genuine anticipation; 3s-6s: reviewer takes a gentle sip or showcases the rich swirl of coffee, speaking passionately with natural mouth movement (lip-sync) about the uplifting energy boost; 6s-8s: close-up on the rising aromatic steam as reviewer smiles refreshed and motivated for the day).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'slide_left',
                    'voiceover_text' => "Mỗi buổi sáng bận rộn, chỉ cần một ly {$brandName} thơm lừng sánh quyện là mọi mệt mỏi tan biến, nạp trọn năng lượng bứt phá cả ngày!",
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Super-macro 8K sensory close-up: Rich velvety {$brandName} dark espresso dripping smoothly into a clear crystal glass cup, forming thick golden hazelnut crema with swirling warm steam rising, roasted coffee beans around pedestal, reviewer {$reviewer} in background admiring the aroma with genuine delight, commercial video quality.",
                    'action_prompt' => "Super slow-motion drip shot (0s-3s: slow-motion macro zoom into thick velvety espresso dripping and blending into rich golden crema inside the glass cup; 3s-6s: in the background, reviewer {$reviewer} gestures expressively with synchronized lip-sync explaining the distinctive authentic flavor and premium roast; 6s-8s: dynamic micro-dolly pan capturing delicate coffee droplets and pristine packaging details with dramatic studio lighting).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'zoom_in',
                    'voiceover_text' => 'Hạt cà phê nguyên bản Đắk Lắk được rang xay theo công nghệ Châu Âu, cho hương thơm nồng nàn và vị đậm đà đánh thức mọi giác quan.',
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Hero showcase & final verdict shot: Reviewer {$reviewer} proudly holding {$brandName} packaging beside a gleaming luxury golden pedestal with soft smoke and sparkling golden particles, giving an enthusiastic thumbs-up, royal crown emblem glowing with soft lens flare, 8K commercial video.",
                    'action_prompt' => "Majestic reveal & call to action (0s-3s: low-angle majestic tilt-up reveal shot showcasing reviewer {$reviewer} holding the {$brandName} hero product with pride and high energy; 3s-6s: reviewer smiles directly into camera, speaking clearly with active lip-sync delivering the final call-to-action verdict; 6s-8s: smooth pull-back framing a bold call-to-action moment while reviewer gives a cheerful thumbs up beside the glowing royal brand badge).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'fade_black',
                    'voiceover_text' => "Đánh giá 10 trên 10 cho chất lượng! Nâng tầm phong cách cùng {$brandName}, các bạn hãy bấm đặt mua ngay hôm nay để nhận ưu đãi đặc quyền nhé!",
                ],
            ];
        }

        if ($targetDuration <= 48) {
            // 6 scenes ~ 48s (Deep Brand Storytelling & Product Review - 8s per scene)
            return [
                [
                    'duration' => 8,
                    'context_prompt' => $hookPrompt,
                    'action_prompt' => $hookAction,
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'glitch',
                    'voiceover_text' => $hookVoiceover,
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Cinematic commercial video: Sun rising over mist-covered Buon Ma Thuot coffee plantations, lush green hills under amber dawn skies, reviewer {$reviewer} holding fresh red coffee cherries.",
                    'action_prompt' => "Panoramic aerial drone fly-through (0s-3s: expansive scenic view over verdant coffee hills; 3s-6s: camera lowers toward reviewer {$reviewer} pointing out the fertile basalt soil with synchronized lip-sync; 6s-8s: smooth tilt showing golden sunrise rays).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'slide_left',
                    'voiceover_text' => 'Bắt đầu từ vùng đất bazan Buôn Ma Thuột huyền thoại, nơi hội tụ tinh hoa đất trời để tạo nên hạt cà phê phẩm cấp cao.',
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Artisan farmer and reviewer {$reviewer} hand-picking ripe crimson red coffee cherries under dappled sunlight, 8K commercial video close-up.",
                    'action_prompt' => "Close-up slow tilt (0s-3s: close-up on pristine fresh coffee cherries in hands; 3s-6s: reviewer {$reviewer} smiling to camera explaining the strict selection process with clear lip-sync; 6s-8s: macro view of pure cherry sweetness).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'zoom_in',
                    'voiceover_text' => 'Từng trái chín mọng được tuyển chọn thủ công tỉ mỉ, giữ trọn vẹn vị ngọt thanh thuần khiết từ tự nhiên.',
                ],
                [
                    'duration' => 8,
                    'context_prompt' => 'State-of-the-art European roasting technology, aromatic beans crackling in copper drum with glowing heat, commercial video lighting.',
                    'action_prompt' => "Dynamic orbit shot (0s-3s: dynamic sweep around roasting drum with sparks and aroma rising; 3s-6s: reviewer {$reviewer} observing the roasting process with keen admiration, speaking with engaging lip-sync; 6s-8s: close-up of dark roasted beans glistening with natural oils).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'wipe_up',
                    'voiceover_text' => 'Công nghệ rang xay chuẩn Châu Âu lưu giữ hoàn hảo hàm lượng tinh chất và hương thơm nồng nàn quyến rũ.',
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Steaming dark {$brandName} coffee dripping through traditional royal gold phin into condensed milk, forming marble layers, 8K commercial video.",
                    'action_prompt' => "Slow macro push-in (0s-3s: slow macro zoom on swirling droplets and rising steam; 3s-6s: reviewer {$reviewer} stirring the cup gently and tasting with pure satisfaction, speaking with natural lip-sync; 6s-8s: rich amber reflections on glass cup).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'cross_dissolve',
                    'voiceover_text' => 'Sự kết hợp hoàn hảo giữa bản sắc văn hóa truyền thống và đẳng cấp quốc tế trong từng ngụm cà phê.',
                ],
                [
                    'duration' => 8,
                    'context_prompt' => "Hero showcase & final verdict shot: Reviewer {$reviewer} proudly holding {$brandName} packaging beside a gleaming luxury golden pedestal with soft smoke and sparkling golden particles, royal crown emblem glowing, 8K commercial video.",
                    'action_prompt' => "Majestic reveal & CTA (0s-3s: low-angle majestic tilt-up reveal shot of the hero product and reviewer {$reviewer}; 3s-6s: reviewer speaks with passionate lip-sync delivering concluding verdict; 6s-8s: bold CTA banner reveal with glowing brand crown badge).",
                    'start_image' => $startAvatar,
                    'end_image' => $startAvatar,
                    'video_url' => '',
                    'transition' => 'fade_black',
                    'voiceover_text' => "{$brandName} - Nâng tầm phong cách, khẳng định vị thế. Các bạn hãy đặt mua ngay hôm nay để nhận ưu đãi đặc quyền nhé!",
                ],
            ];
        }

        // 8 scenes ~ 64s (Full Brand Documentary & Comprehensive Product Review - 8s per scene)
        return [
            [
                'duration' => 8,
                'context_prompt' => $hookPrompt,
                'action_prompt' => $hookAction,
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'glitch',
                'voiceover_text' => $hookVoiceover,
            ],
            [
                'duration' => 8,
                'context_prompt' => "Cinematic commercial video: Sun rising over mist-covered Buon Ma Thuot coffee plantations, lush green hills under amber dawn skies, reviewer {$reviewer} exploring the estate.",
                'action_prompt' => "Panoramic aerial drone fly-through (0s-3s: sweeping vista of coffee hills; 3s-6s: reviewer {$reviewer} gesturing toward the landscape with active lip-sync; 6s-8s: golden dawn light flaring softly).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'slide_left',
                'voiceover_text' => 'Bắt đầu từ vùng đất bazan huyền thoại, nơi hội tụ trọn vẹn tinh hoa đất trời Việt Nam.',
            ],
            [
                'duration' => 8,
                'context_prompt' => "Artisan farmer and reviewer {$reviewer} hand-picking ripe crimson red coffee cherries under dappled sunlight, 8K commercial video.",
                'action_prompt' => "Close-up slow tilt (0s-3s: ripe coffee cherries close-up; 3s-6s: reviewer {$reviewer} praising bean quality with natural lip-sync; 6s-8s: sun-dappled farmer smile).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'zoom_in',
                'voiceover_text' => 'Từng trái chín mọng được thu hoạch thủ công, giữ trọn vẹn vị ngọt thanh thuần khiết từ tự nhiên.',
            ],
            [
                'duration' => 8,
                'context_prompt' => 'State-of-the-art European roasting technology, aromatic beans crackling in copper drum with glowing heat, commercial video.',
                'action_prompt' => "Dynamic orbit shot (0s-3s: swirling roasted beans with sparks; 3s-6s: reviewer {$reviewer} describing aroma depth with synchronized lip-sync; 6s-8s: shiny coffee beans cooling).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'wipe_up',
                'voiceover_text' => 'Công nghệ rang xay chuẩn Châu Âu lưu giữ hàm lượng tinh chất và hương vị quyến rũ bậc nhất.',
            ],
            [
                'duration' => 8,
                'context_prompt' => 'Steaming dark coffee dripping through traditional royal gold phin into condensed milk, forming marble layers, commercial video.',
                'action_prompt' => "Slow macro push-in (0s-3s: mesmerizing coffee drip macro; 3s-6s: reviewer {$reviewer} inhaling the fragrance with expressive lip-sync; 6s-8s: creamy marble swirl).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'cross_dissolve',
                'voiceover_text' => 'Sự kết hợp hoàn hảo giữa bản sắc văn hóa truyền thống và đẳng cấp quốc tế.',
            ],
            [
                'duration' => 8,
                'context_prompt' => "Modern professionals and reviewer {$reviewer} savoring {$brandName} in a high-end sunlit lounge, exchanging smiles and ideas, commercial video.",
                'action_prompt' => "Warm cinematic panning shot (0s-3s: lively luxury lounge atmosphere; 3s-6s: reviewer {$reviewer} sharing energy benefits with engaging lip-sync; 6s-8s: confident cheerful smiles).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'glitch',
                'voiceover_text' => 'Đồng hành cùng hàng triệu con người chinh phục thành công và bứt phá mỗi ngày.',
            ],
            [
                'duration' => 8,
                'context_prompt' => 'Pouring rich dark espresso with velvety thick crema into clear luxury glass cup, golden ambient lighting, commercial video.',
                'action_prompt' => "Super slow-motion drip shot (0s-3s: thick crema swirls; 3s-6s: reviewer {$reviewer} presenting cup with enthusiastic lip-sync; 6s-8s: shimmering dark roast texture).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'zoom_in',
                'voiceover_text' => 'Đánh thức bản lĩnh tiên phong trong từng giọt cà phê đậm đà hảo hạng.',
            ],
            [
                'duration' => 8,
                'context_prompt' => "Hero showcase of {$brandName} complete product collection with golden crown logo and royal packaging glowing against dark velvet backdrop, reviewer {$reviewer} holding hero box, 8K commercial video.",
                'action_prompt' => "Grand sweep reveal shot (0s-3s: sweeping golden particle reveal; 3s-6s: reviewer {$reviewer} delivering authoritative final recommendation with bright lip-sync; 6s-8s: official purchase CTA banner).",
                'start_image' => $startAvatar,
                'end_image' => $startAvatar,
                'video_url' => '',
                'transition' => 'fade_black',
                'voiceover_text' => "{$brandName} - Tuyệt tác cà phê của Vua. Hãy đặt mua ngay hôm nay để nhận ưu đãi đặc quyền!",
            ],
        ];
    }

    /**
     * Generate keyframe start_images for video scenes using AiImageClient.
     *
     * @param  array<int, array<string, mixed>>  $scenes
     */
    private function populateSceneKeyframes(
        array &$scenes,
        Workspace $workspace,
        int $aiImageCount,
        ?string $aiImageStyle,
        ?string $aiImageAspectRatio,
        string $platform,
        ?string $prompt,
        ?string $theme,
        ?string $aiLogoPath,
        ?string $aiImageResolution
    ): void {
        $aiImage = app(AiImageClient::class);
        $styleVal = $aiImageStyle ?? $workspace->image_style;
        $style = $styleVal instanceof ImageStyle ? $styleVal : (ImageStyle::tryFrom((string) $styleVal) ?? ImageStyle::DEFAULT);
        $orientation = $this->resolveOrientation($aiImageAspectRatio ?: '9:16', $platform);

        foreach ($scenes as $sIndex => &$sceneItem) {
            if ($sIndex >= $aiImageCount) {
                break;
            }
            if (empty($sceneItem['start_image'])) {
                $scenePrompt = data_get($sceneItem, 'context_prompt', $prompt ?: $theme ?: 'King Coffee');
                $keyframeResult = $aiImage->generate(
                    keywords: [$theme ?? 'King Coffee'],
                    style: $style,
                    orientation: $orientation,
                    language: $workspace->content_language ?? 'en',
                    customPrompt: $scenePrompt,
                    logoPath: $aiLogoPath ?: null,
                    customResolution: $aiImageResolution ?: null,
                    customAspectRatio: $aiImageAspectRatio ?: '9:16',
                );
                if ($keyframeResult !== null) {
                    $kBytes = $keyframeResult['bytes'];
                    if (filled($aiLogoPath)) {
                        $kBytes = $this->overlayLogo($kBytes, $aiLogoPath);
                    }
                    $savedKeyframe = $this->saveMediaToUserFolder($workspace, $kBytes, 'video-keyframe-'.uniqid().'.jpg');
                    $sceneItem['start_image'] = $savedKeyframe['url'];
                }
            }
        }
        unset($sceneItem);
    }
}
