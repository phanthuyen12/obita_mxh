<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\UserWorkspace\Role;
use App\Jobs\ProcessContentCloneSceneImageJob;
use App\Jobs\ProcessContentCloneSceneVideoJob;
use App\Models\ContentCloneCampaign;
use App\Models\ContentClonePreviewTask;
use App\Models\Folder;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ContentCloneCampaignService;
use App\Services\ContentCloneGenerator;
use App\Services\Dify\DifyWorkflowClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->workflow = $this->workspace->contentWorkflows()->create([
        'name' => 'Duyệt nội dung',
        'created_by' => $this->user->id,
        'is_active' => true,
    ]);
    $this->workflow->members()->attach($this->user->id, [
        'can_write' => true,
        'can_review' => true,
        'can_publish' => true,
    ]);

    $this->sourcePost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'content' => 'Bài viết nguồn về cách xây dựng thương hiệu.',
    ]);
    $this->account = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);
});

test('content clone campaign creates a pending review post for multiple targets', function (): void {
    $secondAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);

    app()->instance(ContentCloneGenerator::class, new class extends ContentCloneGenerator
    {
        public function generate(Workspace $workspace, Post $sourcePost, ?string $theme, ?string $prompt, string $platform, ?string $aiContentMode = 'text_image'): string
        {
            return 'Biến thể mới từ bài nguồn.';
        }
    });

    $campaign = app(ContentCloneCampaignService::class)->create(
        workspace: $this->workspace,
        user: $this->user,
        sourcePost: $this->sourcePost,
        targetSocialAccountIds: [$this->account->id, $secondAccount->id],
        workflowId: $this->workflow->id,
        totalPosts: 2,
        intervalDays: 1,
        startAt: now()->subMinute(),
        theme: 'Xây thương hiệu',
    );

    expect(app(ContentCloneCampaignService::class)->processDue())->toBe(1);

    $clone = Post::query()->where('content', 'Biến thể mới từ bài nguồn.')->firstOrFail();
    expect($clone->workflow_status)->toBe('pending_review')
        ->and($clone->status)->toBe(PostStatus::Draft)
        ->and($clone->content_clone_campaign_id)->toBe($campaign->id)
        ->and($clone->postPlatforms()->where('enabled', true)->count())->toBe(2)
        ->and($campaign->fresh()->generated_posts)->toBe(2);
});

test('content clone campaign stops after its configured number of posts', function (): void {
    app()->instance(ContentCloneGenerator::class, new class extends ContentCloneGenerator
    {
        public function generate(Workspace $workspace, Post $sourcePost, ?string $theme, ?string $prompt, string $platform, ?string $aiContentMode = 'text_image'): string
        {
            return 'Nội dung clone '.$platform;
        }
    });

    $campaign = ContentCloneCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_post_id' => $this->sourcePost->id,
        'content_workflow_id' => $this->workflow->id,
        'created_by' => $this->user->id,
        'target_social_account_ids' => [$this->account->id],
        'total_posts' => 1,
        'generated_posts' => 0,
        'start_at' => now()->subMinute(),
        'next_run_at' => now()->subMinute(),
    ]);

    expect(app(ContentCloneCampaignService::class)->processDue())->toBe(1)
        ->and($campaign->fresh()->is_active)->toBeFalse()
        ->and($campaign->fresh()->generated_posts)->toBe(1);
});

test('content clone preview returns suggestions without creating a campaign', function (): void {
    app()->instance(ContentCloneGenerator::class, new class extends ContentCloneGenerator
    {
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
            return [[
                'content' => 'Gợi ý trước khi lên lịch.',
                'media' => [],
                'provider' => 'default',
            ]];
        }
    });

    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.preview'), [
            'source_post_id' => $this->sourcePost->id,
            'target_social_account_ids' => [$this->account->id],
            'theme' => 'Xây thương hiệu',
            'prompt' => 'Thêm CTA mềm.',
        ])
        ->assertSuccessful();

    $taskId = $response->json('task_id');
    expect($taskId)->not->toBeEmpty();

    $this->getJson(route('app.content-clones.preview-status', ['taskId' => $taskId]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('suggestions.0.content', 'Gợi ý trước khi lên lịch.')
        ->assertJsonPath('suggestions.0.provider', 'default');

    expect(ContentCloneCampaign::query()->count())->toBe(0)
        ->and(Post::query()->where('content', 'Gợi ý trước khi lên lịch.')->exists())->toBeFalse();
});

test('content clone campaign uses selected preview content for the first scheduled review post', function (): void {
    app()->instance(ContentCloneGenerator::class, new class extends ContentCloneGenerator
    {
        public function generate(Workspace $workspace, Post $sourcePost, ?string $theme, ?string $prompt, string $platform, ?string $aiContentMode = 'text_image'): string
        {
            throw new RuntimeException('The first clone should use selected preview content.');
        }
    });

    $campaign = ContentCloneCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_post_id' => $this->sourcePost->id,
        'content_workflow_id' => $this->workflow->id,
        'created_by' => $this->user->id,
        'target_social_account_ids' => [$this->account->id],
        'initial_content' => 'Nội dung đã chọn từ màn hình gợi ý.',
        'initial_media' => [[
            'id' => 'preview-image',
            'url' => 'https://cdn.example/preview.webp',
            'type' => 'image',
            'source' => 'ai',
        ]],
        'total_posts' => 1,
        'generated_posts' => 0,
        'start_at' => now()->subMinute(),
        'next_run_at' => now()->subMinute(),
    ]);

    expect(app(ContentCloneCampaignService::class)->processDue())->toBe(1);

    $clone = Post::query()->where('content', 'Nội dung đã chọn từ màn hình gợi ý.')->firstOrFail();
    expect($clone->workflow_status)->toBe('pending_review')
        ->and($clone->status)->toBe(PostStatus::Draft)
        ->and($clone->content_clone_campaign_id)->toBe($campaign->id)
        ->and(data_get($clone->media, '0.url'))->toBe('https://cdn.example/preview.webp');
});

test('content clone campaign can generate content through a dify workflow', function (): void {
    config([
        'content_clone.ai_provider' => 'dify',
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => [
                'status' => 'succeeded',
                'outputs' => ['content' => 'Bài viết được tạo bởi Dify.'],
            ],
        ]),
    ]);

    $campaign = ContentCloneCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_post_id' => $this->sourcePost->id,
        'content_workflow_id' => $this->workflow->id,
        'created_by' => $this->user->id,
        'target_social_account_ids' => [$this->account->id],
        'total_posts' => 1,
        'generated_posts' => 0,
        'start_at' => now()->subMinute(),
        'next_run_at' => now()->subMinute(),
    ]);

    expect(app(ContentCloneCampaignService::class)->processDue())->toBe(1)
        ->and(Post::query()->where('content', 'Bài viết được tạo bởi Dify.')->exists())->toBeTrue();

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer app-test-key')
            && $request['user'] === "workspace:{$this->workspace->id}"
            && $request['inputs']['source_content'] === $this->sourcePost->content
            && $request['inputs']['platform'] === $this->account->platform->value;
    });
});

test('content clone preview can render multiple dify suggestions', function (): void {
    config([
        'content_clone.ai_provider' => 'dify',
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => [
                'status' => 'succeeded',
                'outputs' => [
                    'suggestions' => [
                        ['content' => 'Gợi ý Dify 1.', 'image_url' => 'https://cdn.example/dify-1.jpg'],
                        ['content' => 'Gợi ý Dify 2.'],
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.preview'), [
            'source_post_id' => $this->sourcePost->id,
            'target_social_account_ids' => [$this->account->id],
            'theme' => 'Xây thương hiệu',
        ])
        ->assertSuccessful();

    $taskId = $response->json('task_id');
    expect($taskId)->not->toBeEmpty();

    $this->getJson(route('app.content-clones.preview-status', ['taskId' => $taskId]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('suggestions.0.content', 'Gợi ý Dify 1.')
        ->assertJsonPath('suggestions.0.media.0.url', 'https://cdn.example/dify-1.jpg')
        ->assertJsonPath('suggestions.1.content', 'Gợi ý Dify 2.');
});

test('content clone campaign can use first dify suggestion when content output is missing', function (): void {
    config([
        'content_clone.ai_provider' => 'dify',
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => [
                'status' => 'succeeded',
                'outputs' => [
                    'suggestions' => [
                        ['content' => 'Suggestion đầu tiên dùng cho campaign.'],
                    ],
                ],
            ],
        ]),
    ]);

    ContentCloneCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source_post_id' => $this->sourcePost->id,
        'content_workflow_id' => $this->workflow->id,
        'created_by' => $this->user->id,
        'target_social_account_ids' => [$this->account->id],
        'total_posts' => 1,
        'generated_posts' => 0,
        'start_at' => now()->subMinute(),
        'next_run_at' => now()->subMinute(),
    ]);

    expect(app(ContentCloneCampaignService::class)->processDue())->toBe(1)
        ->and(Post::query()->where('content', 'Suggestion đầu tiên dùng cho campaign.')->exists())->toBeTrue();
});

test('content clones index page renders successfully and filters social accounts', function (): void {
    $this->actingAs($this->user)
        ->get(route('app.content-clones.index'))
        ->assertSuccessful();
});

test('content clone preview supports video ai mode with structured storyboard scenes', function (): void {
    app()->instance(ContentCloneGenerator::class, new class extends ContentCloneGenerator
    {
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
            return [[
                'content' => "🎬 KỊCH BẢN PHÂN CẢNH & VIDEO AI - KING COFFEE\n\nPhân cảnh 1: Cận cảnh ly cà phê King Coffee...",
                'media' => [],
                'provider' => 'default',
                'video_scenes' => $videoScenes,
            ]];
        }
    });

    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.preview'), [
            'source_post_id' => $this->sourcePost->id,
            'target_social_account_ids' => [$this->account->id],
            'ai_content_mode' => 'video_ai',
            'theme' => 'King Coffee Espresso Hoàng Gia',
            'video_scenes' => [
                [
                    'duration' => 4,
                    'context_prompt' => 'Ly cà phê King Coffee sữa đá sang trọng',
                    'action_prompt' => 'Slow push-in zoom',
                    'voiceover_text' => 'Bừng tỉnh năng lượng cùng King Coffee.',
                ],
            ],
        ])
        ->assertSuccessful();

    $taskId = $response->json('task_id');
    expect($taskId)->not->toBeEmpty();

    $this->getJson(route('app.content-clones.preview-status', ['taskId' => $taskId]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('suggestions.0.video_scenes.0.voiceover_text', 'Bừng tỉnh năng lượng cùng King Coffee.');
});

test('cleanFacebookPostContent strips headings and returns pure natural facebook post', function (): void {
    $generator = new ContentCloneGenerator;

    $rawDifyText = <<<'TXT'
**BÀI VIẾT MẠNG XÃ HỘI (FACEBOOK)**

---

🔥 **TIÊU ĐỀ:**
✨ **Trung Thu Đậm Vị – Nâng Tầm Khoảnh Khắc Đoàn Viên Cùng King Coffee 3in1! Ưu Đãi Vàng Đón Trăng Rằm!** ✨

📝 **NỘI DUNG CHÍNH:**

Mùa trăng tròn lung linh trở lại, mang theo những khoảnh khắc sum vầy ấm áp bên gia đình và người thân.

*   ☕ **Hương Vị Đậm Đà:** Cà phê nguyên chất tuyển chọn.
*   🌟 **Trải Nghiệm Đẳng Cấp:** Dù là khởi đầu ngày mới.

🎉 **GIẢM NGAY 20% KHI MUA CÀ PHÊ HÒA TAN KING COFFEE 3IN1!** 🎉

🎯 **LỜI KÊU GỌI HÀNH ĐỘNG (CTA):**
Đừng bỏ lỡ cơ hội nâng tầm hương vị Trung Thu và sẻ chia niềm vui cùng King Coffee!

🏷️ **BỘ HASHTAGS THỊNH HÀNH:**
#KingCoffee #KingCoffee3in1 #TrungThuDamVi

**5. 🎨 GỢI Ý HÌNH ẢNH/VISUAL MINH HỌA:**
*   **Loại hình:** Poster vuông 1:1.
*   **Tông màu chủ đạo:** Đỏ burgundy.
TXT;

    $cleaned = $generator->cleanFacebookPostContent($rawDifyText);

    expect($cleaned)->not->toContain('BÀI VIẾT MẠNG XÃ HỘI')
        ->not->toContain('TIÊU ĐỀ:')
        ->not->toContain('NỘI DUNG CHÍNH:')
        ->not->toContain('LỜI KÊU GỌI HÀNH ĐỘNG')
        ->not->toContain('BỘ HASHTAGS THỊNH HÀNH')
        ->not->toContain('GỢI Ý HÌNH ẢNH')
        ->not->toContain('Loại hình:')
        ->toContain('Trung Thu Đậm Vị – Nâng Tầm Khoảnh Khắc')
        ->toContain('Mùa trăng tròn lung linh trở lại')
        ->toContain('#KingCoffee');
});

test('saveMediaToUserFolder creates user email folder in media library and stores media record', function (): void {
    Storage::fake();

    $generator = new ContentCloneGenerator;
    $fakeBytes = 'fake-image-bytes-'.uniqid();

    $saved = $generator->saveMediaToUserFolder(
        workspace: $this->workspace,
        mediaItemOrBytes: $fakeBytes,
        originalFilename: 'custom-ai-photo.jpg',
        user: $this->user,
    );

    expect($saved)->toHaveKeys(['id', 'url', 'path', 'folder_id', 'original_filename'])
        ->and($saved['original_filename'])->toBe('custom-ai-photo.jpg');

    $folder = Folder::query()
        ->where('workspace_id', $this->workspace->id)
        ->where('name', $this->user->email)
        ->first();

    expect($folder)->not->toBeNull()
        ->and($folder->name)->toBe($this->user->email);

    $media = Media::query()->find($saved['id']);
    expect($media)->not->toBeNull()
        ->and($media->folder_id)->toBe($folder->id)
        ->and($media->workspace_id)->toBe($this->workspace->id)
        ->and($media->uploaded_by)->toBe($this->user->id);
});

test('resolveOrientation properly resolves portrait, square, and landscape', function (): void {
    $generator = new ContentCloneGenerator;

    expect($generator->resolveOrientation('9:16'))->toBe('portrait')
        ->and($generator->resolveOrientation('4:5'))->toBe('portrait')
        ->and($generator->resolveOrientation('1:1'))->toBe('square')
        ->and($generator->resolveOrientation('16:9'))->toBe('landscape')
        ->and($generator->resolveOrientation(null, 'tiktok'))->toBe('portrait')
        ->and($generator->resolveOrientation(null, 'instagram'))->toBe('square')
        ->and($generator->resolveOrientation(null, 'facebook'))->toBe('landscape');
});

test('buildFinalImagePrompt builds rich commercial prompt', function (): void {
    $generator = new ContentCloneGenerator;

    $this->workspace->update([
        'name' => 'King Coffee Luxury',
        'brand_description' => 'Thuong hieu ca phe hang dau the gioi',
    ]);

    $prompt = $generator->buildFinalImagePrompt(
        workspace: $this->workspace,
        customImagePrompt: null,
        theme: 'Espresso Hoang Gia',
        imageTitle: 'Dam Vi Ca Phe',
        imageBody: 'Khoi nguon sang tao'
    );

    expect($prompt)->toContain('King Coffee Luxury')
        ->toContain('Espresso Hoang Gia')
        ->toContain('Dam Vi Ca Phe')
        ->toContain('Khoi nguon sang tao')
        ->toContain('ultra-realistic 8k UHD');
});

test('buildDefaultVideoScenes generates structured scenes for King Coffee', function (): void {
    $generator = new ContentCloneGenerator;

    $scenes = $generator->buildDefaultVideoScenes(
        workspace: $this->workspace,
        sourcePost: $this->sourcePost,
        theme: 'Festival Ca Phe',
        prompt: 'Tap trung vao huong thom'
    );

    expect($scenes)->toBeArray()
        ->toHaveCount(4)
        ->and($scenes[0])->toHaveKeys(['duration', 'context_prompt', 'action_prompt', 'start_image', 'end_image', 'transition', 'voiceover_text'])
        ->and($scenes[0]['duration'])->toBe(8)
        ->and($scenes[1]['duration'])->toBe(8)
        ->and($scenes[0]['context_prompt'])->toContain('commercial video');
});

test('generate-scene-image dispatches ProcessContentCloneSceneImageJob and returns task_id', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.generate-scene-image'), [
            'prompt' => 'Ly cà phê sáng đầy năng lượng',
            'theme' => 'King Coffee',
            'aspect_ratio' => '9:16',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['success', 'task_id']);

    Queue::assertPushed(ProcessContentCloneSceneImageJob::class);
});

test('generate-scene-video dispatches ProcessContentCloneSceneVideoJob and returns task_id', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.generate-scene-video'), [
            'action_prompt' => 'Camera zoom chậm vào tách cà phê bốc khói',
            'context_prompt' => 'Quán cà phê sang trọng',
            'duration' => 8,
            'theme' => 'King Coffee',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['success', 'task_id']);

    Queue::assertPushed(ProcessContentCloneSceneVideoJob::class);
});

test('preview-status returns task results correctly', function (): void {
    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'completed',
        'payload' => ['prompt' => 'test'],
        'suggestions' => [
            'url' => 'https://example.com/scene.jpg',
            'video_url' => 'https://example.com/scene.mp4',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.content-clones.preview-status', ['taskId' => $task->id]));

    $response->assertSuccessful();
    $response->assertJson([
        'status' => 'completed',
        'url' => 'https://example.com/scene.jpg',
        'video_url' => 'https://example.com/scene.mp4',
    ]);
});

test('ProcessContentCloneSceneVideoJob executes and extracts video_url', function (): void {
    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'action_prompt' => 'Camera zoom chậm vào sản phẩm ly cà phê',
            'image_url' => 'https://example.com/product.jpg',
            'duration' => 8,
            'theme' => 'King Coffee',
        ],
    ]);

    $generator = app(ContentCloneGenerator::class);
    $job = new ProcessContentCloneSceneVideoJob($task);
    $job->handle($generator);

    $task->refresh();
    expect($task->status)->toBe('completed');
    expect($task->suggestions)->toHaveKey('video_url');
    expect($task->suggestions['video_url'])->toBeString()->not->toBeEmpty();
});

test('ProcessContentCloneSceneVideoJob extracts video_files string url from Dify output correctly', function (): void {
    config(['content_clone.ai_provider' => 'dify']);
    config(['services.dify.api_key' => 'fake-key']);

    $mockDify = Mockery::mock(DifyWorkflowClient::class);
    $mockDify->shouldReceive('run')
        ->once()
        ->andReturn([
            'video_files' => 'https://cdn.revidapi.com/outputs/6770f468-b7e0-42cc-b70e-50b6c8ebb399/result.mp4',
            'video_script' => 'Kịch bản video AI cho King Coffee',
        ]);
    app()->instance(DifyWorkflowClient::class, $mockDify);

    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'action_prompt' => 'Camera zoom chậm vào sản phẩm',
            'image_url' => 'https://example.com/product.jpg',
            'duration' => 8,
            'theme' => 'King Coffee',
        ],
    ]);

    $generator = app(ContentCloneGenerator::class);
    $job = new ProcessContentCloneSceneVideoJob($task);
    $job->handle($generator);

    $task->refresh();
    expect($task->status)->toBe('completed');
    expect($task->suggestions['video_url'])->toBe('https://cdn.revidapi.com/outputs/6770f468-b7e0-42cc-b70e-50b6c8ebb399/result.mp4');
});

test('generate-scene-voiceover endpoint returns audio url', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.generate-scene-voiceover'), [
            'text' => 'Thưởng thức hương vị King Coffee đậm đà nguyên bản.',
            'voice' => 'vi_vn_female_warm',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'audio_url',
        'voice',
        'duration',
    ]);
    expect($response->json('audio_url'))->toBeString()->not->toBeEmpty();
});

test('ProcessContentCloneSceneVideoJob includes character DNA and speech dialogue in prompt inputs', function (): void {
    config(['content_clone.ai_provider' => 'dify']);
    config(['services.dify.api_key' => 'fake-key']);

    $mockDify = Mockery::mock(DifyWorkflowClient::class);
    $mockDify->shouldReceive('run')
        ->once()
        ->withArgs(function (array $inputs, string $user = 'laravel-ai:video') {
            return str_contains($inputs['requirement'], 'Hoàng Nam')
                && str_contains($inputs['requirement'], 'Thưởng thức hương vị đậm đà')
                && $inputs['character_name'] === 'Hoàng Nam'
                && $inputs['voiceover_text'] === 'Thưởng thức hương vị đậm đà';
        })
        ->andReturn([
            'video_files' => 'https://cdn.example.com/character-speaking-video.mp4',
        ]);
    app()->instance(DifyWorkflowClient::class, $mockDify);

    $task = ContentClonePreviewTask::create([
        'workspace_id' => $this->workspace->id,
        'status' => 'pending',
        'payload' => [
            'action_prompt' => 'Nhân vật nói câu chào',
            'image_url' => 'https://example.com/barista.jpg',
            'duration' => 8,
            'theme' => 'King Coffee',
            'voiceover_text' => 'Thưởng thức hương vị đậm đà',
            'voice' => 'vi_vn_male_deep',
            'character_name' => 'Hoàng Nam',
            'character_dna' => 'Barista nam 28 tuổi',
        ],
    ]);

    $generator = app(ContentCloneGenerator::class);
    $job = new ProcessContentCloneSceneVideoJob($task);
    $job->handle($generator);

    $task->refresh();
    expect($task->status)->toBe('completed');
    expect($task->suggestions['video_url'])->toBe('https://cdn.example.com/character-speaking-video.mp4');
});

test('generate-video-scenes endpoint returns scripted scenes for given hook and duration', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.content-clones.generate-video-scenes'), [
            'video_hook' => 'King CF HÀ NỘI, GIỚI THIỆU VỀ SẢN PHẨM',
            'video_target_duration' => 32,
            'character_name' => 'Hoàng Nam',
            'character_dna' => 'Barista 28 tuổi',
            'character_avatar' => 'https://example.com/nam.jpg',
        ]);

    $response->assertSuccessful();
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['video_scenes'])->toBeArray()->toHaveCount(4);
    expect($data['video_scenes'][0]['duration'])->toBe(8);
    expect($data['video_scenes'][0]['start_image'])->toBe('https://example.com/nam.jpg');
    expect($data['video_scenes'][0]['action_prompt'])->toContain('0s-3s');
    expect($data['video_scenes'][0]['voiceover_text'])->not->toBeEmpty();
});
