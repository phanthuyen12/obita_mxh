<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContentClonePreviewTask;
use App\Services\ContentCloneGenerator;
use App\Services\Dify\DifyWorkflowClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessContentCloneSceneVideoJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

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

        $fallbackClips = [
            'https://assets.mixkit.co/videos/preview/mixkit-coffee-beans-falling-into-a-sack-41584-large.mp4',
            'https://assets.mixkit.co/videos/preview/mixkit-barista-making-a-latte-art-coffee-41582-large.mp4',
            'https://assets.mixkit.co/videos/preview/mixkit-steaming-cup-of-coffee-in-morning-sun-41585-large.mp4',
        ];
        $selectedFallbackClip = $fallbackClips[array_rand($fallbackClips)];

        try {
            $workspace = $this->task->workspace;
            $payload = $this->task->payload;

            $brandName = $workspace?->name ?? 'King Coffee';
            $contextPrompt = trim((string) ($payload['context_prompt'] ?? ''));
            $actionPrompt = trim((string) ($payload['action_prompt'] ?? ''));
            $rawPrompt = trim((string) ($payload['prompt'] ?? ''));

            // Combine both visual context and action motion so AI has full 8s review storyboard context
            $prompt = collect([$contextPrompt, $actionPrompt])->filter()->implode('. ');
            if (blank($prompt)) {
                $prompt = $rawPrompt ?: 'Cinematic slow motion King Coffee commercial';
            }
            $imageUrl = $payload['image_url'] ?? null;
            $duration = (int) ($payload['duration'] ?? 8);
            $aspectRatio = (string) ($payload['aspect_ratio'] ?? '9:16');
            $theme = (string) ($payload['theme'] ?? 'King Coffee');
            $platform = (string) ($payload['platform'] ?? 'facebook');
            $videoHook = (string) ($payload['video_hook'] ?? '');
            $sourceContent = Str::limit(trim((string) ($payload['source_content'] ?? '')), 800);
            $voiceoverText = Str::limit(trim((string) ($payload['voiceover_text'] ?? '')), 500);
            $voice = trim((string) ($payload['voice'] ?? 'vi_vn_female_warm'));
            $characterName = trim((string) ($payload['character_name'] ?? ''));
            $characterDna = Str::limit(trim((string) ($payload['character_dna'] ?? '')), 500);
            $characterAvatar = trim((string) ($payload['character_avatar'] ?? ''));

            // Strict product preservation constraints (both Vietnamese and English)
            $productConstraintVi = collect([
                'QUY TẮC BẮT BUỘC SỐ 1 - BẢO TỒN NGUYÊN VẸN 100% HÌNH ẢNH SẢN PHẨM GỐC:',
                '- Sử dụng chính xác 100% hình ảnh sản phẩm thực tế từ ảnh tham chiếu (Product / imageProduct / image_url): giữ nguyên bao bì, vỏ hộp, ly/cốc/chai, nhãn mác, chữ, màu sắc và logo thương hiệu.',
                '- Tuyệt đối KHÔNG thay đổi thiết kế sản phẩm, KHÔNG làm biến dạng bao bì, KHÔNG thay thế bằng sản phẩm khác, KHÔNG làm sai lệch nhận diện thương hiệu.',
                '- PHẠM VI CHUYỂN ĐỘNG VIDEO: Chuyển động trong video CHỈ LÀ chuyển động góc máy quay điện ảnh (camera motion: slow push-in, smooth pan, subtle dynamic zoom, orbital sweep), hiệu ứng ánh sáng studio điện ảnh (cinematic lighting, soft flare), và khói/hơi nước/môi trường nền xung quanh sản phẩm. Sản phẩm gốc luôn là chủ thể trung tâm giữ nguyên vẹn 100% độ sắc nét và hình dạng.',
            ])->implode("\n");

            $productConstraintEn = collect([
                'STRICT PRODUCT PRESERVATION MANDATE (CRITICAL):',
                '- Absolute fidelity to original product from reference image: Maintain 100% exact packaging, logo, typography, shape, and colors. Do NOT modify, distort, mutate, hallucinate, or replace the product with any other item.',
                '- Video motion MUST be strictly camera movement (smooth cinematic push-in, gentle zoom, orbital pan, tilt) and background environmental lighting/steam effects. The physical product itself must remain completely stable, intact, and authentic.',
            ])->implode("\n");

            // Character DNA & Spoken Dialogue Constraints
            $characterConstraintVi = filled($characterDna) ? collect([
                'BẢO TỒN ĐỒNG NHẤT NHÂN VẬT AI (CHARACTER DNA CONSISTENCY):',
                '- Nhân vật: '.($characterName ?: 'Nhân vật đại diện King Coffee').". Đặc tả DNA cố định: {$characterDna}.",
                '- Bắt buộc giữ nguyên 100% nhận diện khuôn mặt, trang phục, phong thái của nhân vật này, không được thay đổi hoặc làm sai lệch qua các phân cảnh.',
            ])->implode("\n") : null;

            $speechConstraintVi = filled($voiceoverText) ? collect([
                'LỜI THOẠI NHÂN VẬT NÓI TRONG VIDEO (SPOKEN DIALOGUE / VOICEOVER AI):',
                '- Nhân vật '.($characterName ? "{$characterName} " : '')."nói to rõ ràng câu thoại sau trong video: \"{$voiceoverText}\".",
                '- Khẩu hình miệng (lip-sync), ánh mắt và biểu cảm của nhân vật chuyển động khớp tự nhiên và chân thực với từng từ trong câu nói này.',
                "- Giọng đọc: {$voice}, phát âm chuẩn tiếng Việt, truyền cảm hứng và chuyên nghiệp.",
            ])->implode("\n") : null;

            $speechConstraintEn = filled($voiceoverText) ? collect([
                'CHARACTER SPOKEN DIALOGUE & LIP SYNC (CRITICAL):',
                "- The character speaks the following lines aloud in the video: \"{$voiceoverText}\".",
                '- Synchronize mouth movement (lip-sync) and expressive facial animation naturally matching the spoken words.',
                "- Voice tone: {$voice}, clear, engaging, and premium commercial quality.",
            ])->implode("\n") : null;

            $instructions = Str::limit(collect([
                "Bạn là Đạo Diễn & Biên Kịch Video Quảng Cáo AI cho thương hiệu {$brandName}.",
                'NHIỆM VỤ: Xây dựng video thương mại ngắn (15s - 30s) chuyên nghiệp, ấn tượng bám sát yêu cầu người dùng.',
                $productConstraintVi,
                $productConstraintEn,
                $characterConstraintVi,
                $speechConstraintVi,
                $speechConstraintEn,
                $theme ? "Chủ đề chiến dịch: {$theme}" : null,
                $actionPrompt ? "Timeline hành động: {$actionPrompt}" : null,
                $contextPrompt ? "Bối cảnh thị giác: {$contextPrompt}" : null,
                $voiceoverText ? "Lời thoại nhân vật nói: \"{$voiceoverText}\"" : null,
                $videoHook ? "Hook video: {$videoHook}" : null,
                $sourceContent ? "Nội dung nguồn: {$sourceContent}" : null,
            ])->filter()->implode("\n\n"), 1800);

            $requirement = Str::limit(collect([
                $actionPrompt ? "DIỄN BIẾN HÀNH ĐỘNG CHI TIẾT (8S ACTION TIMELINE): {$actionPrompt}" : ($prompt ? "Yêu cầu cảnh quay: {$prompt} (Giữ nguyên 100% bao bì và hình ảnh sản phẩm gốc, chuyển động góc máy camera điện ảnh)." : null),
                $contextPrompt ? "BỐI CẢNH & KHÔNG GIAN THỊ GIÁC: {$contextPrompt}" : null,
                $speechConstraintVi,
                $characterConstraintVi,
                $productConstraintVi,
                $theme ? "Chủ đề: {$theme}" : null,
                $videoHook ? "Hook: {$videoHook}" : null,
                "Nền tảng: {$platform}",
                $sourceContent ? "Nội dung nguồn: {$sourceContent}" : null,
            ])->filter()->implode("\n\n") ?: $prompt, 2200);

            $inputs = [
                'content_type' => 'video',
                'requirement' => $requirement ?: $prompt,
                'Product' => filled($imageUrl) ? [
                    [
                        'type' => 'image',
                        'transfer_method' => 'remote_url',
                        'url' => $imageUrl,
                    ],
                ] : [],
                'theme' => $theme,
                'prompt' => collect([
                    $contextPrompt ?: $prompt,
                    $actionPrompt ? "Timeline hành động 8s: {$actionPrompt}" : null,
                    filled($voiceoverText) ? "Nhân vật nói to rõ ràng câu thoại: \"{$voiceoverText}\" (khẩu hình cử động khớp lời nói lip-sync)." : null,
                    'RÀNG BUỘC: Giữ nguyên 100% hình ảnh sản phẩm gốc từ ảnh tham chiếu, chỉ chuyển động camera điện ảnh và ánh sáng xung quanh.',
                ])->filter()->implode(' '),
                'action_prompt' => collect([
                    $actionPrompt ?: $prompt,
                    filled($voiceoverText) ? "Reviewer speaks clearly with synchronized natural lip-sync: \"{$voiceoverText}\"." : null,
                    'Strict product preservation: Keep original product packaging and appearance intact.',
                ])->filter()->implode(' '),
                'context_prompt' => $contextPrompt,
                'platform' => $platform,
                'video_hook' => $videoHook,
                'brand_name' => $brandName,
                'source_content' => $sourceContent,
                'instructions' => $instructions,
                'imageProduct' => $imageUrl ?: '',
                'image_url' => $imageUrl ?: '',
                'product_image_url' => $imageUrl ?: '',
                'voiceover_text' => $voiceoverText,
                'dialogue' => $voiceoverText,
                'spoken_dialogue' => $voiceoverText,
                'speech_text' => $voiceoverText,
                'voice' => $voice,
                'character_name' => $characterName,
                'character_dna' => $characterDna,
                'character_avatar' => $characterAvatar,
                'duration' => (string) match (true) {
                    $duration > 12 => '15',
                    $duration >= 8 => '10',
                    default => '6',
                },
                'aspect_ratio' => $aspectRatio,
                'preserve_product_identity' => true,
                'preserve_product' => true,
                'product_constraint' => "{$productConstraintVi}\n\n{$productConstraintEn}",
                'negative_prompt' => 'distorted product, deformed packaging, altered logo, fake brand, mutated bottle, morphed cup, changing label, blurry product, low quality, glitch, cartoonish product modification, wrong product',
            ];

            Log::info('ProcessContentCloneSceneVideoJob: Bắt đầu chạy tạo video', [
                'task_id' => $this->task->id,
                'inputs' => $inputs,
            ]);

            if (config('content_clone.ai_provider') === 'dify' || filled(config('services.dify.api_key'))) {
                try {
                    $dify = app(DifyWorkflowClient::class);
                    $outputs = $dify->run($inputs, 'laravel-ai:video');

                    $videoUrl = $this->extractVideoUrlFromOutputs($outputs);

                    if (filled($videoUrl)) {
                        $finalVideoUrl = $this->attachVoiceoverToVideo($videoUrl, $voiceoverText, $voice);

                        $this->task->update([
                            'status' => 'completed',
                            'suggestions' => [
                                'video_url' => $finalVideoUrl,
                            ],
                        ]);

                        return;
                    }
                } catch (\Throwable $e) {
                    Log::warning('ProcessContentCloneSceneVideoJob: Dify video call failed: '.$e->getMessage());
                }
            }

            // Fallback video clip with voiceover attached
            $finalFallback = $this->attachVoiceoverToVideo($selectedFallbackClip, $voiceoverText, $voice);

            $this->task->update([
                'status' => 'completed',
                'suggestions' => [
                    'video_url' => $finalFallback,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessContentCloneSceneVideoJob error: '.$e->getMessage());

            $this->task->update([
                'status' => 'completed',
                'suggestions' => [
                    'video_url' => $selectedFallbackClip,
                ],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract a valid video URL from Dify workflow outputs.
     *
     * @param  array<string, mixed>  $outputs
     */
    private function extractVideoUrlFromOutputs(array $outputs): ?string
    {
        Log::info('ProcessContentCloneSceneVideoJob: Phân tích outputs từ Dify', [
            'outputs' => $outputs,
        ]);

        // 1. Direct candidate keys (can be string URL or array)
        $candidateKeys = [
            'video_files',
            'video_url',
            'video',
            'result_video',
            'output_video',
            'result_url',
            'video_file',
            'url',
            'result',
        ];

        foreach ($candidateKeys as $key) {
            $val = data_get($outputs, $key);

            // Case A: Key directly holds a string URL
            if (filled($val) && is_string($val) && $this->isVideoUrl($val)) {
                Log::info("ProcessContentCloneSceneVideoJob: Tìm thấy video URL ở key '{$key}'", ['url' => $val]);

                return trim($val);
            }

            // Case B: Key holds an array (e.g. array of urls or objects)
            if (is_array($val) && ! empty($val)) {
                foreach ($val as $item) {
                    $itemUrl = is_array($item) ? ($item['url'] ?? $item['remote_url'] ?? null) : $item;
                    $itemType = is_array($item) ? ($item['type'] ?? $item['mime_type'] ?? '') : '';

                    if (filled($itemUrl) && is_string($itemUrl)) {
                        if (str_contains((string) $itemType, 'video') || $this->isVideoUrl($itemUrl)) {
                            Log::info("ProcessContentCloneSceneVideoJob: Tìm thấy video URL trong mảng '{$key}'", ['url' => $itemUrl]);

                            return trim($itemUrl);
                        }
                    }
                }
            }
        }

        // 2. Check general 'files' array
        $files = data_get($outputs, 'files');
        if (is_array($files)) {
            foreach ($files as $file) {
                $fileUrl = is_array($file) ? ($file['url'] ?? $file['remote_url'] ?? null) : $file;
                $fileType = is_array($file) ? ($file['type'] ?? $file['mime_type'] ?? '') : '';

                if (filled($fileUrl) && is_string($fileUrl)) {
                    if (str_contains((string) $fileType, 'video') || $this->isVideoUrl($fileUrl)) {
                        Log::info('ProcessContentCloneSceneVideoJob: Tìm thấy video URL trong files', ['url' => $fileUrl]);

                        return trim($fileUrl);
                    }
                }
            }
        }

        // 3. Scan any string in outputs for an embedded video URL (.mp4, .mov, etc.)
        foreach ($outputs as $k => $val) {
            if (is_string($val)) {
                if (preg_match('/https?:\/\/[^\s"\'<>]+\.(mp4|webm|mov|m4v)(\?[^\s"\'<>]*)?/i', $val, $matches)) {
                    Log::info("ProcessContentCloneSceneVideoJob: Trích xuất regex video URL từ field '{$k}'", ['url' => $matches[0]]);

                    return trim($matches[0]);
                }
            }
        }

        Log::warning('ProcessContentCloneSceneVideoJob: Không tìm thấy video URL hợp lệ từ Dify outputs, sử dụng fallback', [
            'outputs' => $outputs,
        ]);

        return null;
    }

    /**
     * Check if a URL points to a video file or video stream.
     */
    private function isVideoUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (Str::endsWith($path, ['.mp4', '.webm', '.mov', '.m4v', '.ogv', '.mkv'])) {
            return true;
        }

        return Str::contains(strtolower($url), ['video', 'mp4', 'stream', 'revidapi']);
    }

    /**
     * Ensure video file contains the voiceover audio track directly in the video file.
     */
    private function attachVoiceoverToVideo(string $videoUrl, ?string $voiceoverText, string $voice): string
    {
        if (blank($voiceoverText)) {
            return $videoUrl;
        }

        try {
            $ffmpegPath = $this->getFfmpegPath();
            if (! $ffmpegPath) {
                Log::info('ProcessContentCloneSceneVideoJob: Không tìm thấy ffmpeg binary, giữ video URL gốc');

                return $videoUrl;
            }

            // 1. Generate Voice Audio via OpenAI TTS
            $voiceAudioContent = $this->generateVoiceAudio($voiceoverText, $voice);
            if (blank($voiceAudioContent)) {
                return $videoUrl;
            }

            $tempDir = storage_path('app/temp_media');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $uniqueId = Str::random(12);
            $tempAudioPath = "{$tempDir}/voice_{$uniqueId}.mp3";
            $tempVideoPath = "{$tempDir}/video_in_{$uniqueId}.mp4";
            $outputVideoPath = "{$tempDir}/video_out_{$uniqueId}.mp4";

            file_put_contents($tempAudioPath, $voiceAudioContent);

            // 2. Download video content to local temp file
            $videoContent = Http::timeout(35)->get($videoUrl)->body();
            if (blank($videoContent)) {
                @unlink($tempAudioPath);

                return $videoUrl;
            }
            file_put_contents($tempVideoPath, $videoContent);

            // 3. Run FFmpeg to merge voiceover into video
            // -c:v copy preserves video stream without re-encoding
            // -c:a aac encodes clean audio
            // -shortest matches duration
            $cmd = escapeshellcmd($ffmpegPath).' -y -i '.escapeshellarg($tempVideoPath).' -i '.escapeshellarg($tempAudioPath).' -c:v copy -c:a aac -map 0:v:0 -map 1:a:0 -shortest '.escapeshellarg($outputVideoPath).' 2>&1';
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0 && file_exists($outputVideoPath) && filesize($outputVideoPath) > 0) {
                $finalFilename = "content-clones/videos/scene_voice_{$uniqueId}.mp4";
                Storage::disk('public')->put($finalFilename, file_get_contents($outputVideoPath));
                $newVideoUrl = Storage::disk('public')->url($finalFilename);

                @unlink($tempAudioPath);
                @unlink($tempVideoPath);
                @unlink($outputVideoPath);

                Log::info('ProcessContentCloneSceneVideoJob: Đã ghép voiceover trực tiếp vào video thành công!', [
                    'video_url' => $newVideoUrl,
                ]);

                return $newVideoUrl;
            }

            Log::warning('ProcessContentCloneSceneVideoJob: FFmpeg merge failed', [
                'cmd' => $cmd,
                'output' => $output,
                'return_code' => $returnVar,
            ]);

            @unlink($tempAudioPath);
            @unlink($tempVideoPath);
            @unlink($outputVideoPath);
        } catch (\Throwable $e) {
            Log::warning('ProcessContentCloneSceneVideoJob: Lỗi khi gắn voiceover vào video: '.$e->getMessage());
        }

        return $videoUrl;
    }

    /**
     * Generate speech audio from text using OpenAI TTS.
     */
    private function generateVoiceAudio(string $text, string $voice): ?string
    {
        $openaiKey = config('services.openai.api_key');
        if (filled($openaiKey)) {
            try {
                $voiceMap = [
                    'vi_vn_female_warm' => 'nova',
                    'vi_vn_male_deep' => 'onyx',
                    'vi_vn_female_sweet' => 'shimmer',
                    'vi_vn_male_friendly' => 'echo',
                ];
                $openaiVoice = $voiceMap[$voice] ?? 'nova';

                $response = Http::withToken($openaiKey)
                    ->timeout(25)
                    ->post('https://api.openai.com/v1/audio/speech', [
                        'model' => 'tts-1',
                        'input' => $text,
                        'voice' => $openaiVoice,
                        'response_format' => 'mp3',
                    ]);

                if ($response->successful() && filled($response->body())) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                Log::warning('ProcessContentCloneSceneVideoJob TTS call failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Find available ffmpeg executable path on system.
     */
    private function getFfmpegPath(): ?string
    {
        $possiblePaths = [
            '/opt/homebrew/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/usr/bin/ffmpeg',
            'ffmpeg',
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'ffmpeg' || (file_exists($path) && is_executable($path))) {
                return $path;
            }
        }

        return null;
    }
}
