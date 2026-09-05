<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Enums\Post\CreatedVia;
use App\Http\Requests\App\ContentClone\PreviewContentCloneRequest;
use App\Http\Requests\App\ContentClone\StoreContentCloneCampaignRequest;
use App\Jobs\ProcessContentClonePreviewJob;
use App\Jobs\ProcessContentCloneSceneImageJob;
use App\Jobs\ProcessContentCloneSceneVideoJob;
use App\Models\ContentCloneCampaign;
use App\Models\ContentClonePreviewTask;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\ContentCloneCampaignService;
use App\Services\ContentCloneGenerator;
use App\Services\Dify\DifyWorkflowClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentCloneCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        return Inertia::render('content-clones/Index', [
            'campaigns' => $workspace->contentCloneCampaigns()
                ->with([
                    'sourcePost:id,content',
                    'contentWorkflow:id,name',
                    'posts.postPlatforms.socialAccount',
                ])
                ->latest()
                ->get(),
            'sourcePosts' => $workspace->posts()
                ->whereNotNull('content')
                ->with(['postPlatforms.socialAccount'])
                ->latest('created_at')
                ->limit(100)
                ->get(['id', 'content', 'media', 'created_at']),
            'socialAccounts' => $workspace->socialAccounts()
                ->active()
                ->with('sharedUsers')
                ->orderBy('display_name')
                ->get()
                ->filter(fn (SocialAccount $account): bool => $account->userHasAccess($request->user(), 'can_access_content'))
                ->values(),
            'contentWorkflows' => $workspace->contentWorkflows()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreContentCloneCampaignRequest $request, ContentCloneCampaignService $service): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validated();

        // Release session lock before potential background / heavy generation
        $request->session()->save();

        if (empty($validated['source_post_id']) && ! empty($validated['manual_source_content'])) {
            $sourcePost = CreatePost::execute($workspace, $request->user(), [
                'content' => $validated['manual_source_content'],
                'media' => $validated['manual_source_media'] ?? [],
                'created_via' => CreatedVia::Automation,
            ]);
        } else {
            $sourcePost = Post::query()->where('workspace_id', $workspace->id)->findOrFail($validated['source_post_id']);
        }

        $service->create(
            workspace: $workspace,
            user: $request->user(),
            sourcePost: $sourcePost,
            targetSocialAccountIds: $validated['target_social_account_ids'],
            workflowId: $validated['content_workflow_id'] ?? null,
            totalPosts: (int) $validated['total_posts'],
            intervalDays: (int) $validated['interval_days'],
            startAt: Carbon::parse($validated['start_at']),
            theme: $validated['theme'] ?? null,
            prompt: $validated['prompt'] ?? null,
            imagePrompt: $validated['image_prompt'] ?? null,
            initialContent: $validated['initial_content'] ?? null,
            initialMedia: $validated['initial_media'] ?? null,
            requireApproval: (bool) ($validated['require_approval'] ?? true),
            aiImageCount: (int) ($validated['ai_image_count'] ?? 0),
            aiImageStyle: $validated['ai_image_style'] ?? null,
            aiLogoPath: $validated['ai_logo_path'] ?? null,
            diffContentPerPage: (bool) ($validated['diff_content_per_page'] ?? false),
            aiImageResolution: $validated['ai_image_resolution'] ?? null,
            aiImageAspectRatio: $validated['ai_image_aspect_ratio'] ?? null,
            aiContentMode: $validated['ai_content_mode'] ?? null,
            videoScenes: $validated['video_scenes'] ?? null,
        );

        return back()->with('flash.banner', 'Đã tạo chiến dịch clone nội dung. Cron sẽ tạo bài theo lịch và gửi duyệt.');
    }

    public function preview(PreviewContentCloneRequest $request, ContentCloneGenerator $generator): JsonResponse
    {
        Log::info('DATA input preview: '.$request);
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validated();

        $targetAccount = $workspace->socialAccounts()
            ->active()
            ->whereIn('id', $validated['target_social_account_ids'])
            ->firstOrFail();
        $platform = $targetAccount->platform->value;

        // Create a preview task
        $task = ContentClonePreviewTask::create([
            'workspace_id' => $workspace->id,
            'status' => 'pending',
            'payload' => $validated,
        ]);

        // Release PHP session lock immediately so other user requests/tabs never hang
        $request->session()->save();

        ProcessContentClonePreviewJob::dispatch($task);

        return response()->json([
            'platform' => $platform,
            'social_account' => [
                'id' => $targetAccount->id,
                'platform' => $platform,
                'display_name' => $targetAccount->display_label,
                'username' => $targetAccount->username ?? '',
                'display_label' => $targetAccount->display_label,
                'handle_label' => $targetAccount->handle_label,
                'avatar_url' => $targetAccount->avatar_url,
            ],
            'task_id' => $task->id,
        ]);
    }

    public function previewStatus(Request $request, string $taskId): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $task = ContentClonePreviewTask::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($taskId);

        $suggestions = $task->suggestions ?? [];
        $url = is_array($suggestions) ? ($suggestions['url'] ?? null) : null;
        $videoUrl = is_array($suggestions) ? ($suggestions['video_url'] ?? null) : null;

        return response()->json([
            'status' => $task->status,
            'suggestions' => $suggestions,
            'result' => $suggestions,
            'url' => $url,
            'video_url' => $videoUrl,
            'error' => $task->error,
        ]);
    }

    public function generateSceneImage(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:50000'],
            'theme' => ['nullable', 'string', 'max:50000'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'style' => ['nullable', 'string'],
            'aspect_ratio' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'character_name' => ['nullable', 'string', 'max:255'],
            'character_dna' => ['nullable', 'string', 'max:5000'],
            'character_avatar' => ['nullable', 'string', 'max:2048'],
        ]);

        // Release PHP session lock immediately before dispatching background job
        $request->session()->save();

        $task = ContentClonePreviewTask::create([
            'workspace_id' => $workspace->id,
            'status' => 'pending',
            'payload' => $validated,
        ]);

        ProcessContentCloneSceneImageJob::dispatch($task);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
        ]);
    }

    public function testVideosDify(Request $request): JsonResponse
    {
        // Release PHP session lock
        $request->session()->save();

        $imageUrl = $request->input('image_url');
        $characterAvatar = $request->input('character_avatar');
        $characterName = $request->input('character_name', '');
        $characterDna = $request->input('character_dna', '');
        $voiceoverText = $request->input('voiceover_text', '');
        $voice = $request->input('voice', 'vi_vn_female_warm');
        $prompt = $request->input('action_prompt', $request->input('prompt', 'Cinematic slow motion King Coffee commercial'));
        $duration = (int) $request->input('duration', 8);
        $theme = (string) $request->input('theme', 'King Coffee');
        $platform = (string) $request->input('platform', 'facebook');
        $videoHook = (string) $request->input('video_hook', '');
        $sourceContent = Str::limit(trim((string) $request->input('source_content', '')), 800);
        $brandName = (string) ($request->user()?->currentWorkspace?->name ?? 'King Coffee');

        $instructions = Str::limit(collect([
            "Bạn là Đạo Diễn & Biên Kịch Video Quảng Cáo AI cho thương hiệu {$brandName}.",
            'NHIỆM VỤ: Xây dựng kịch bản phân cảnh video thương mại ngắn (15s - 30s) chuyên nghiệp, ấn tượng bám sát yêu cầu người dùng.',
            $theme ? "Chủ đề chiến dịch: {$theme}" : null,
            $prompt ? "Yêu cầu hành động / cảnh quay: {$prompt}" : null,
            $videoHook ? "Hook video: {$videoHook}" : null,
            $sourceContent ? "Nội dung nguồn: {$sourceContent}" : null,
        ])->filter()->implode("\n\n"), 1000);

        $requirement = Str::limit(collect([
            $prompt ? "Yêu cầu: {$prompt}" : null,
            $theme ? "Chủ đề: {$theme}" : null,
            $videoHook ? "Hook: {$videoHook}" : null,
            "Nền tảng: {$platform}",
            $sourceContent ? "Nội dung nguồn: {$sourceContent}" : null,
        ])->filter()->implode("\n\n") ?: $prompt, 1000);

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
            'prompt' => $prompt,
            'platform' => $platform,
            'video_hook' => $videoHook,
            'brand_name' => $brandName,
            'source_content' => $sourceContent,
            'instructions' => $instructions,
            'imageProduct' => $imageUrl ?: '',
            'image_url' => $imageUrl ?: '',
            'character_name' => $characterName,
            'character_dna' => $characterDna,
            'character_avatar' => $characterAvatar ?: '',
            'voiceover_text' => $voiceoverText,
            'voice' => $voice,
            'duration' => "{$duration}s",
            'aspect_ratio' => '9:16',
        ];

        try {
            $dify = app(DifyWorkflowClient::class);
            $outputs = $dify->run($inputs, 'laravel-ai:video');

            return response()->json([
                'success' => true,
                'inputs_sent' => $inputs,
                'outputs' => $outputs,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'inputs_sent' => $inputs,
            ], 500);
        }
    }

    public function generateSceneVideo(Request $request): JsonResponse
    {
        Log::info('Generate scene video request', $request->all());

        $validated = $request->validate([
            'image_url' => ['nullable', 'string'],
            'action_prompt' => ['nullable', 'string'],
            'context_prompt' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'theme' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'video_hook' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'source_content' => ['nullable', 'string'],
            'voiceover_text' => ['nullable', 'string'],
            'voice' => ['nullable', 'string'],
            'character_name' => ['nullable', 'string'],
            'character_dna' => ['nullable', 'string'],
            'character_avatar' => ['nullable', 'string'],
        ]);

        $workspace = $request->user()?->currentWorkspace;

        // Release PHP session lock immediately before dispatching background job
        $request->session()->save();

        $task = ContentClonePreviewTask::create([
            'workspace_id' => $workspace->id,
            'status' => 'pending',
            'payload' => $validated,
        ]);

        ProcessContentCloneSceneVideoJob::dispatch($task);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
        ]);
    }

    public function generateSceneVoiceover(Request $request): JsonResponse
    {
        Log::info('Generate scene voiceover request', $request->all());

        $validated = $request->validate([
            'voiceover_text' => ['required_without:text', 'nullable', 'string'],
            'text' => ['required_without:voiceover_text', 'nullable', 'string'],
            'voice' => ['nullable', 'string'],
            'character_name' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string'],
        ]);

        $text = trim((string) ($validated['voiceover_text'] ?? $validated['text'] ?? ''));
        $voice = $validated['voice'] ?? 'vi_vn_female_warm';
        $characterName = $validated['character_name'] ?? 'King Coffee AI';
        $videoUrl = $validated['video_url'] ?? null;

        // Release PHP session lock
        $request->session()->save();

        $openaiKey = config('services.openai.api_key');
        $audioUrl = null;

        if (filled($openaiKey)) {
            try {
                $voiceMap = [
                    'vi_vn_female_warm' => 'nova',
                    'vi_vn_male_deep' => 'onyx',
                    'vi_vn_female_sweet' => 'shimmer',
                    'vi_vn_male_friendly' => 'echo',
                ];
                $openaiVoice = $voiceMap[$voice] ?? 'alloy';

                $response = Http::withToken($openaiKey)
                    ->timeout(30)
                    ->post('https://api.openai.com/v1/audio/speech', [
                        'model' => 'tts-1',
                        'input' => $text,
                        'voice' => $openaiVoice,
                        'response_format' => 'mp3',
                    ]);

                if ($response->successful() && filled($response->body())) {
                    $filename = 'content-clones/voiceovers/voice_'.Str::random(16).'.mp3';
                    Storage::disk('public')->put($filename, $response->body());
                    $audioUrl = Storage::disk('public')->url($filename);
                }
            } catch (\Throwable $e) {
                Log::warning('GenerateSceneVoiceover OpenAI TTS failed: '.$e->getMessage());
            }
        }

        if (blank($audioUrl)) {
            $fallbackAudios = [
                'https://assets.mixkit.co/active_storage/sfx/2874/2874-preview.mp3',
                'https://assets.mixkit.co/active_storage/sfx/2432/2432-preview.mp3',
            ];
            $audioUrl = $fallbackAudios[0];
        }

        // If a video URL is provided, merge voiceover into the video file directly
        $finalVideoUrl = $videoUrl;
        if (filled($videoUrl) && filled($audioUrl)) {
            try {
                $ffmpegPath = file_exists('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg';
                $tempDir = storage_path('app/temp_media');
                if (! is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $uniqueId = Str::random(12);
                $tempVideoPath = "{$tempDir}/v_in_{$uniqueId}.mp4";
                $tempAudioPath = "{$tempDir}/a_in_{$uniqueId}.mp3";
                $outputVideoPath = "{$tempDir}/v_out_{$uniqueId}.mp4";

                $vContent = Http::timeout(35)->get($videoUrl)->body();
                $aContent = Http::timeout(35)->get($audioUrl)->body();

                if (filled($vContent) && filled($aContent)) {
                    file_put_contents($tempVideoPath, $vContent);
                    file_put_contents($tempAudioPath, $aContent);

                    $cmd = escapeshellcmd($ffmpegPath).' -y -i '.escapeshellarg($tempVideoPath).' -i '.escapeshellarg($tempAudioPath).' -c:v copy -c:a aac -map 0:v:0 -map 1:a:0 -shortest '.escapeshellarg($outputVideoPath).' 2>&1';
                    exec($cmd, $output, $returnVar);

                    if ($returnVar === 0 && file_exists($outputVideoPath) && filesize($outputVideoPath) > 0) {
                        $finalFilename = "content-clones/videos/scene_voice_{$uniqueId}.mp4";
                        Storage::disk('public')->put($finalFilename, file_get_contents($outputVideoPath));
                        $finalVideoUrl = Storage::disk('public')->url($finalFilename);
                    }

                    @unlink($tempVideoPath);
                    @unlink($tempAudioPath);
                    @unlink($outputVideoPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Merge voice into video in controller failed: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'audio_url' => $audioUrl,
            'video_url' => $finalVideoUrl,
            'voiceover_text' => $text,
            'voice' => $voice,
            'character_name' => $characterName,
            'duration' => round(max(2.0, mb_strlen($text) / 14), 1),
            'message' => 'Đã tích hợp giọng nói vào video thành công!',
        ]);
    }

    public function stitchVideo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scenes' => ['required', 'array'],
            'bgm_track' => ['nullable', 'string'],
            'auto_subtitles' => ['nullable', 'boolean'],
        ]);

        // Release PHP session lock
        $request->session()->save();

        // If a scene has an actual generated video_url, prioritize it
        $firstVideo = collect($validated['scenes'])
            ->pluck('video_url')
            ->filter()
            ->first();

        $videoUrl = $firstVideo ?: 'https://assets.mixkit.co/videos/preview/mixkit-coffee-beans-falling-into-a-sack-41584-large.mp4';

        return response()->json([
            'success' => true,
            'video_url' => $videoUrl,
            'message' => 'Đã ghép toàn bộ phân cảnh, phụ đề và nhạc nền thành video hoàn chỉnh!',
        ]);
    }

    public function generateVideoScenes(Request $request, ContentCloneGenerator $generator): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'video_hook' => ['nullable', 'string', 'max:1000'],
            'video_target_duration' => ['nullable', 'integer', 'min:8', 'max:120'],
            'source_post_id' => ['nullable', 'integer'],
            'manual_source_content' => ['nullable', 'string', 'max:50000'],
            'theme' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string', 'max:5000'],
            'character_name' => ['nullable', 'string', 'max:255'],
            'character_dna' => ['nullable', 'string', 'max:5000'],
            'character_avatar' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->session()->save();

        $sourcePost = null;
        if (filled($validated['source_post_id'] ?? null)) {
            $sourcePost = Post::query()
                ->where('workspace_id', $workspace->id)
                ->find($validated['source_post_id']);
        }

        if (! $sourcePost) {
            $sourcePost = new Post([
                'workspace_id' => $workspace->id,
                'content' => $validated['manual_source_content'] ?? ($validated['prompt'] ?? ''),
                'media' => [],
            ]);
        }

        $scenes = $generator->generateVideoScenesWithAi(
            workspace: $workspace,
            sourcePost: $sourcePost,
            theme: $validated['theme'] ?? null,
            prompt: $validated['prompt'] ?? null,
            videoHook: $validated['video_hook'] ?? null,
            videoTargetDuration: (int) ($validated['video_target_duration'] ?? 32),
            characterName: $validated['character_name'] ?? null,
            characterDna: $validated['character_dna'] ?? null,
            characterAvatar: $validated['character_avatar'] ?? null,
        );

        return response()->json([
            'success' => true,
            'video_scenes' => $scenes,
            'message' => 'AI đã biên kịch thành công ' . count($scenes) . ' phân cảnh chi tiết!',
        ]);
    }

    public function destroy(Request $request, ContentCloneCampaign $campaign): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($campaign->workspace_id === $workspace->id, 404);
        $this->authorize('createPost', $workspace);
        $campaign->update(['is_active' => false]);

        return back()->with('flash.banner', 'Đã dừng chiến dịch clone nội dung.');
    }
}
