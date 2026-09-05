<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Actions\Post\DeletePost;
use App\Actions\Post\DuplicatePost;
use App\Actions\Post\SyncPostPlatforms;
use App\Actions\Post\UpdatePost;
use App\Actions\SocialAccount\ListPinterestBoards;
use App\Ai\Templates\AiContentTemplate;
use App\Ai\Templates\AiTemplateRegistry;
use App\Enums\Folder\Permission;
use App\Enums\Notification\Channel;
use App\Enums\Notification\Type as NotificationType;
use App\Enums\Post\Action as PostAction;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\App\Post\MovePostRequest;
use App\Http\Requests\App\Post\StorePostRequest;
use App\Http\Requests\App\Post\UpdatePostRequest;
use App\Http\Resources\Api\PostResource;
use App\Http\Resources\App\SocialAccountResource;
use App\Jobs\SendNotification;
use App\Mail\PostWorkflowApproved;
use App\Mail\PostWorkflowRejected;
use App\Mail\PostWorkflowSubmitted;
use App\Models\Folder;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Services\Post\PostMetricsFetcher;
use App\Services\Social\TikTokCreatorInfo;
use App\Support\PostStatusRules;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request, ?string $status = null): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $query = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount'), 'user', 'labels', 'tags', 'folder:id,name']);

        $requestedStatus = $status ?? $request->string('status')->toString();
        $statusFilter = PostStatus::tryFrom($requestedStatus);

        if ($statusFilter !== null) {
            $query = match ($statusFilter) {
                PostStatus::Draft => $query->draft()->latest('updated_at'),
                PostStatus::Scheduled => $query->scheduled()->orderBy('scheduled_at', 'asc'),
                PostStatus::Published => $query->published()->latest('published_at'),
                PostStatus::PartiallyPublished => $query->where('status', PostStatus::PartiallyPublished)->latest('published_at'),
                PostStatus::Publishing => $query->where('status', PostStatus::Publishing)->latest('updated_at'),
                PostStatus::Failed => $query->failed()->latest('updated_at'),
                default => $query->latest('created_at'),
            };
        } else {
            $query->latest('created_at');
        }

        if ($search = $request->input('search')) {
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('content', $likeOperator, "%{$search}%");
        }

        $labelIds = $request->collect('labels')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();

        $query->when($labelIds, fn ($q) => $q->whereHas(
            'labels',
            fn ($q) => $q->whereIn('workspace_labels.id', $labelIds),
        ));

        $platform = Platform::tryFrom($request->string('platform')->toString());
        $query->when($platform, fn ($q) => $q->whereHas(
            'postPlatforms',
            fn ($platforms) => $platforms->enabled()->where('platform', $platform),
        ));

        $folderId = $request->string('folder_id')->toString();
        $query->when($folderId === 'unfiled', fn ($q) => $q->whereNull('folder_id'))
            ->when($folderId !== '' && $folderId !== 'unfiled', fn ($q) => $q->where('folder_id', $folderId));

        $workflowId = $request->string('workflow_id')->toString();
        $query->when($workflowId === 'none', fn ($q) => $q->whereNull('content_workflow_id'))
            ->when($workflowId !== '' && $workflowId !== 'none', fn ($q) => $q->where('content_workflow_id', $workflowId));

        $workflowStatus = $request->string('workflow_status')->toString();
        $allowedWorkflowStatuses = ['draft', 'pending_review', 'approved', 'rejected'];
        $query->when(in_array($workflowStatus, $allowedWorkflowStatuses, true), fn ($q) => $q->where('workflow_status', $workflowStatus));

        $topicTag = trim($request->string('topic_tag')->toString());
        $query->when($topicTag !== '' && mb_strlen($topicTag) <= 100, fn ($q) => $q->whereHas(
            'tags',
            fn ($tags) => $tags->where('tags.workspace_id', $workspace->id)->where('tags.name', $topicTag),
        ));

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return Inertia::render('posts/Index', [
            'workspace' => $workspace,
            'posts' => Inertia::scroll(fn () => $query->paginate(config('app.pagination.default'))),
            'currentStatus' => $statusFilter?->value,
            'labels' => $workspace->labels()->orderBy('name')->get(['id', 'name', 'color']),
            'topicTags' => $workspace->tags()
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'folders' => $this->postFolders($request)->map->only(['id', 'name']),
            'contentWorkflows' => $workspace->contentWorkflows()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'platformOptions' => collect(Platform::cases())->map(fn (Platform $platform): array => [
                'value' => $platform->value,
                'label' => $platform->label(),
            ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'labels' => $labelIds,
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'status' => $statusFilter?->value ?? '',
                'platform' => $platform?->value ?? '',
                'folder_id' => $folderId,
                'workflow_id' => $workflowId,
                'workflow_status' => in_array($workflowStatus, $allowedWorkflowStatuses, true) ? $workflowStatus : '',
                'topic_tag' => mb_strlen($topicTag) <= 100 ? $topicTag : '',
            ],
        ]);
    }

    public function calendar(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $tz = 'UTC';
        $view = $request->input('view', 'week');

        $currentDay = $request->input('day')
            ? Carbon::parse($request->input('day'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'), $tz)->startOfWeek()
            : Carbon::now($tz)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $monthDate = $request->input('month')
            ? Carbon::parse($request->input('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfWeek();

        $rangeStart = match ($view) {
            'day' => $currentDay,
            'month' => $monthStart,
            default => $weekStart,
        };
        $rangeEnd = match ($view) {
            'day' => $currentDay->copy()->endOfDay(),
            'month' => $monthEnd,
            default => $weekEnd,
        };

        $posts = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount'), 'folder:id,name'])
            ->whereBetween('scheduled_at', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($post) => $post->scheduled_at?->setTimezone($tz)->format('Y-m-d'));

        $calendarStats = $workspace->posts()
            ->whereBetween('scheduled_at', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN workflow_status = 'pending_review' THEN 1 ELSE 0 END) AS pending_review")
            ->selectRaw("SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled")
            ->selectRaw("SUM(CASE WHEN status IN ('published', 'partially_published') THEN 1 ELSE 0 END) AS published")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->first();

        return Inertia::render('posts/Calendar', [
            'workspace' => $workspace,
            'posts' => $posts,
            'currentDay' => $currentDay->format('Y-m-d'),
            'currentWeekStart' => $weekStart->format('Y-m-d'),
            'currentMonth' => $monthDate->format('Y-m-d'),
            'view' => $view,
            'stats' => [
                'total' => (int) $calendarStats->total,
                'pending_review' => (int) $calendarStats->pending_review,
                'scheduled' => (int) $calendarStats->scheduled,
                'published' => (int) $calendarStats->published,
                'failed' => (int) $calendarStats->failed,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $registry = app(AiTemplateRegistry::class);

        $templates = array_map(fn (AiContentTemplate $t) => [
            'key' => $t->key(),
            'name' => trans($t->name()),
            'description' => trans($t->description()),
            'preview' => $t->previewAsset(),
            'needs_account' => $t->needsAccount(),
            'supported_formats' => $t->supportedFormats(),
            'applies_brand_visuals' => $t->appliesBrandVisuals(),
        ], $registry->all());

        return Inertia::render('posts/Create', [
            'date' => $request->query('date'),
            'socialAccounts' => SocialAccountResource::collection(
                $workspace->socialAccounts()->accessibleBy($request->user())->active()->whereIn('platform', Platform::publishingValues())->get()
                    ->filter(fn (SocialAccount $account): bool => $account->userHasAccess($request->user(), 'can_access_content'))
            ),
            'contentWorkflows' => $workspace->contentWorkflows()
                ->where('is_active', true)
                ->with('socialAccount')
                ->orderBy('name')
                ->get(['id', 'name', 'social_account_id']),
            'folders' => $this->postFolders($request),
            'templates' => $templates,
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);

        if ($request->validated('folder_id')) {
            $folder = Folder::query()->findOrFail($request->validated('folder_id'));
            abort_unless($folder->userHasPermission($request->user(), Permission::View), 403);
        }

        $socialAccounts = $workspace->socialAccounts()
            ->accessibleBy($request->user())
            ->active()
            ->whereIn('platform', Platform::publishingValues())
            ->get()
            ->filter(fn (SocialAccount $account): bool => $account->userHasAccess($request->user(), 'can_access_content'))
            ->values();

        if ($socialAccounts->isEmpty()) {
            session()->flash('flash.banner', __('posts.flash.connect_first'));
            session()->flash('flash.bannerStyle', 'danger');

            return $request->user()->can('manageAccounts', $workspace)
                ? redirect()->route('app.accounts')
                : redirect()->route('app.calendar');
        }

        $post = CreatePost::execute($workspace, $request->user(), [
            'date' => $request->input('date'),
            'media' => $request->input('media', []),
            'content_workflow_id' => $request->input('content_workflow_id'),
            'folder_id' => $request->input('folder_id'),
            'created_via' => CreatedVia::Web,
        ]);

        return Inertia::location(route('app.posts.edit', $post));
    }

    public function platformMetrics(Request $request, Post $post, PostPlatform $postPlatform): JsonResponse
    {
        $this->authorize('view', $post);

        if ($postPlatform->post_id !== $post->id) {
            abort(404);
        }

        return response()->json(app(PostMetricsFetcher::class)->forPlatform($postPlatform));
    }

    public function show(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $post);

        if (in_array($post->status, [PostStatus::Draft, PostStatus::Scheduled], true)) {
            return redirect()->route('app.posts.edit', $post);
        }

        $post->load(['postPlatforms.socialAccount', 'labels', 'contentWorkflow.members']);
        $workflowMember = $post->contentWorkflow?->members?->firstWhere('id', $request->user()->id);

        return Inertia::render('posts/Show', [
            'workspace' => $workspace,
            'post' => (new PostResource($post))->resolve(),
        ]);
    }

    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return redirect()->route('app.posts.show', $post);
        }

        if ($request->user()->can('update', $post)) {
            SyncPostPlatforms::execute($post, $request->user());
        }

        $post->load([
            'postPlatforms' => fn ($query) => $query->select([
                'id',
                'post_id',
                'social_account_id',
                'enabled',
                'platform',
                'platform_name',
                'platform_username',
                'platform_avatar',
                'content_type',
                'status',
                'platform_url',
                'error_message',
                'published_at',
                'meta',
            ]),
            'postPlatforms.socialAccount' => fn ($query) => $query->select([
                'id',
                'platform',
                'platform_user_id',
                'username',
                'display_name',
                'avatar_url',
                'meta',
            ]),
            'postPlatforms.socialAccount.groups:id,name',
            'labels',
            'tags',
            'contentWorkflow.members',
        ]);
        $workflowMember = $post->contentWorkflow?->members?->firstWhere('id', $request->user()->id);
        $selectedSocialAccounts = $post->postPlatforms
            ->where('enabled', true)
            ->pluck('socialAccount')
            ->filter()
            ->unique('id')
            ->values();
        $labels = $workspace->labels;
        $signatures = $workspace->signatures;
        Tag::ensureDefaultTags($workspace);
        $postTags = $workspace->tags()->orderBy('name')->get(['id', 'name', 'color']);

        $platformConfigs = collect(Platform::publishingValues())
            ->mapWithKeys(function (string $platformValue): array {
                $platform = Platform::from($platformValue);

                return [$platformValue => [
                    'platform' => $platformValue,
                    'maxContentLength' => $platform->maxContentLength(),
                    'maxImages' => $platform->maxImages(),
                    'allowedMediaTypes' => array_map(
                        fn ($type): string => $type->value,
                        $platform->allowedMediaTypes(),
                    ),
                    'supportsTextOnly' => $platform->supportsTextOnly(),
                    'requiresContent' => $platform->requiresContent(),
                    'publishConfig' => $platform->publishConfig(),
                ]];
            });

        $pinterestBoards = $selectedSocialAccounts
            ->where('platform', Platform::Pinterest)
            ->mapWithKeys(fn ($account) => [
                $account->id => rescue(
                    fn () => ListPinterestBoards::execute($account),
                    ['boards' => [], 'truncated' => false],
                    report: false,
                ),
            ]);

        $tiktokCreatorInfos = $selectedSocialAccounts
            ->where('platform', Platform::TikTok)
            ->mapWithKeys(fn ($account) => [
                $account->id => rescue(
                    fn () => app(TikTokCreatorInfo::class)->fetch($account),
                    null,
                    report: false,
                ),
            ])
            ->filter();

        return Inertia::render('posts/Edit', [
            'workspace' => $workspace,
            'post' => $post,
            'platformConfigs' => $platformConfigs,
            'pinterestBoards' => $pinterestBoards,
            'tiktokCreatorInfos' => $tiktokCreatorInfos,
            'labels' => $labels,
            'signatures' => $signatures,
            'postTags' => $postTags,
            'socialAccountGroups' => $workspace->socialAccountGroups()
                ->orderBy('name')
                ->get(['id', 'name']),
            'contentWorkflows' => $workspace->contentWorkflows()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'folders' => $this->postFolders($request),
            'channelBrowserUrl' => route('app.posts.channels', $post),
            'authUserId' => $request->user()->id,
            'wordPressSites' => $workspace->wordPressSites()
                ->where('status', 'connected')
                ->get(['id', 'name', 'url', 'username', 'categories_cache', 'tags_cache']),
            'workflow' => [
                'name' => $post->contentWorkflow?->name,
                'status' => $post->workflow_status,
                'note' => $post->workflow_note,
                'can_write' => $request->user()->can('manageTeam', $workspace) || (bool) $workflowMember?->pivot?->can_write,
                'can_review' => $request->user()->can('manageTeam', $workspace) || (bool) $workflowMember?->pivot?->can_review,
                'can_publish' => $request->user()->can('manageTeam', $workspace) || (bool) $workflowMember?->pivot?->can_publish,
            ],
        ]);
    }

    public function channels(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);
        abort_unless($post->workspace_id === $request->user()->currentWorkspace?->id, 404);

        $perPage = min(max($request->integer('per_page', 50), 12), 50);
        $channels = $post->postPlatforms()
            ->with(['socialAccount:id,platform,username,display_name,avatar_url', 'socialAccount.groups:id,name'])
            ->orderBy('platform_name')
            ->paginate($perPage);

        return response()->json($channels);
    }

    public function move(MovePostRequest $request, Post $post): JsonResponse
    {
        $post->update(['folder_id' => $request->validated('folder_id')]);

        return response()->json(['data' => $post->fresh()->load('folder:id,name')]);
    }

    private function postFolders(Request $request): Collection
    {
        return Folder::query()
            ->forWorkspace($request->user()->currentWorkspace)
            ->get()
            ->filter(fn (Folder $folder): bool => $folder->userHasPermission(
                $request->user(),
                Permission::View,
            ))
            ->values();
    }

    public function submitForReview(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('update', $post);
        abort_unless($post->workspace_id === $workspace?->id, 404);

        $post->loadMissing('contentWorkflow.members');
        $member = $post->contentWorkflow?->members?->firstWhere('id', $request->user()->id);
        abort_unless($request->user()->can('manageTeam', $workspace) || (bool) $member?->pivot?->can_write, 403);

        $scheduledAt = $this->validatedWorkflowSchedule($request);
        $post->update([
            'workflow_status' => 'pending_review',
            'workflow_note' => null,
            'scheduled_at' => $scheduledAt,
        ]);
        $this->notifyWorkflowRole($post, 'can_review', 'Bài viết cần duyệt', 'Có một bài viết mới đang chờ bạn duyệt.');

        return back()->with('flash.banner', 'Đã gửi bài viết để duyệt.');
    }

    public function approveWorkflow(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $post);
        abort_unless($post->workspace_id === $workspace?->id, 404);

        $post->loadMissing('contentWorkflow.members');
        $member = $post->contentWorkflow?->members?->firstWhere('id', $request->user()->id);
        $isWorkflowReviewer = (bool) $member?->pivot?->can_review;
        $isWorkspaceAdmin = $request->user()->can('manageTeam', $workspace);

        abort_unless($isWorkspaceAdmin || $isWorkflowReviewer, 403, 'Bạn chưa được cấp quyền duyệt bài cho bài viết này.');

        $post->loadMissing('postPlatforms.socialAccount');
        if (! $isWorkspaceAdmin && ! $isWorkflowReviewer) {
            abort_unless(
                $post->postPlatforms->filter(fn (PostPlatform $platform): bool => $platform->enabled)
                    ->every(fn (PostPlatform $platform): bool => $platform->socialAccount?->userHasAccess($request->user(), 'can_approve_posts') ?? false),
                403,
                'Bạn chưa được cấp quyền duyệt bài cho các Page của bài viết.',
            );
        }
        abort_unless($post->workflow_status === 'pending_review', 422, 'Bài viết chưa ở trạng thái chờ duyệt.');

        $scheduledAt = $this->validatedWorkflowSchedule($request, $post->scheduled_at);

        $post->update([
            'workflow_status' => 'approved',
            'workflow_note' => null,
            'scheduled_at' => $scheduledAt,
            'status' => PostStatus::Scheduled,
        ]);

        $post->loadMissing('user', 'workspace');

        // Gửi email & notification cho người đảm nhận (tác giả bài viết)
        if ($post->user) {
            SendNotification::dispatch(
                user: $post->user,
                workspaceId: $post->workspace_id,
                type: NotificationType::PostApproved,
                channel: Channel::Both,
                title: 'Bài viết đã được duyệt',
                body: "Bài viết của bạn trong workspace {$post->workspace->name} đã được phê duyệt thành công.",
                data: ['post_id' => $post->id, 'workflow_id' => $post->contentWorkflow?->id],
                mailable: new PostWorkflowApproved($post),
            );
        }

        $this->notifyWorkflowRole($post, 'can_publish', 'Bài viết đã được duyệt', 'Bài viết đã được duyệt và sẵn sàng đăng.');

        return back()->with('flash.banner', 'Đã duyệt bài viết.');
    }

    private function validatedWorkflowSchedule(Request $request, ?DateTimeInterface $fallback = null): Carbon
    {
        $validated = validator([
            'scheduled_at' => $request->input('scheduled_at') ?? $fallback?->format('Y-m-d H:i:s'),
        ], [
            'scheduled_at' => ['required', 'date', 'after:now'],
        ], [
            'scheduled_at.required' => 'Vui lòng chọn thời gian đăng trước khi gửi duyệt.',
            'scheduled_at.after' => 'Thời gian đăng phải ở tương lai.',
        ])->validate();

        return Carbon::parse($validated['scheduled_at'])->utc();
    }

    public function rejectWorkflow(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $post);
        abort_unless($post->workspace_id === $workspace?->id, 404);

        $post->loadMissing('contentWorkflow.members', 'user', 'workspace');
        $member = $post->contentWorkflow?->members?->firstWhere('id', $request->user()->id);
        abort_unless($request->user()->can('manageTeam', $workspace) || (bool) $member?->pivot?->can_review, 403);
        abort_unless($post->workflow_status === 'pending_review', 422, 'Bài viết chưa ở trạng thái chờ duyệt.');

        $note = (string) $request->input('note', 'Vui lòng chỉnh sửa lại nội dung.');

        $post->update([
            'workflow_status' => 'rejected',
            'workflow_note' => $note,
        ]);

        // Gửi email & notification cho người đảm nhận (tác giả bài viết)
        if ($post->user) {
            SendNotification::dispatch(
                user: $post->user,
                workspaceId: $post->workspace_id,
                type: NotificationType::PostRejected,
                channel: Channel::Both,
                title: 'Bài viết bị từ chối / cần chỉnh sửa',
                body: "Bài viết của bạn trong workspace {$post->workspace->name} đã bị từ chối. Lý do: {$note}",
                data: ['post_id' => $post->id, 'workflow_id' => $post->contentWorkflow?->id],
                mailable: new PostWorkflowRejected($post, $note),
            );
        }

        return back()->with('flash.banner', 'Đã trả bài viết về để chỉnh sửa.');
    }

    private function notifyWorkflowRole(Post $post, string $permission, string $title, string $body): void
    {
        $post->loadMissing('user', 'workspace');

        foreach ($post->contentWorkflow?->members ?? [] as $member) {
            if (! $member->pivot->{$permission}) {
                continue;
            }

            $mailable = match ($permission) {
                'can_review' => new PostWorkflowSubmitted($post),
                'can_publish' => new PostWorkflowApproved($post),
                default => null,
            };

            SendNotification::dispatch(
                user: $member,
                workspaceId: $post->workspace_id,
                type: NotificationType::PostReady,
                channel: Channel::Both,
                title: $title,
                body: $body,
                data: ['post_id' => $post->id, 'workflow_id' => $post->contentWorkflow?->id],
                mailable: $mailable,
            );
        }
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('update', $post);

        $validated = $request->validated();

        if ($post->content_workflow_id && ! $request->user()->can('manageTeam', $workspace)) {
            $post->loadMissing('contentWorkflow.members');
            $canPublish = (bool) $post->contentWorkflow?->members
                ->firstWhere('id', $request->user()->id)?->pivot?->can_publish;

            if (! $canPublish) {
                $validated['scheduled_at'] = null;
            }
        }

        if (array_key_exists('folder_id', $validated) && $validated['folder_id'] !== null) {
            $folder = Folder::query()->findOrFail($validated['folder_id']);
            abort_unless($folder->userHasPermission($request->user(), Permission::View), 403);
        }
        $publishingStatus = in_array(
            data_get($validated, 'status'),
            [PostStatus::Publishing->value, PostStatus::Scheduled->value],
            true,
        );

        if ($publishingStatus && $post->content_workflow_id) {
            if (
                ! $request->user()->can('manageTeam', $workspace)
                && $post->workflow_status !== 'approved'
            ) {
                return back()->withErrors([
                    'status' => 'Bài viết cần được duyệt trước khi đăng.',
                ]);
            }

            $canPublish = $request->user()->can('manageTeam', $workspace)
                || (bool) $post->contentWorkflow?->members
                    ->firstWhere('id', $request->user()->id)?->pivot?->can_publish;

            if (! $canPublish) {
                return back()->withErrors([
                    'status' => 'Bạn chưa được cấp quyền đăng bài trong luồng này.',
                ]);
            }
        }

        if ($publishingStatus) {
            $post->loadMissing('postPlatforms.socialAccount');
            $selectedPlatforms = collect($validated['platforms'] ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn (string $id) => $post->postPlatforms->firstWhere('id', $id))
                ->filter();

            abort_unless(
                $selectedPlatforms->every(fn (PostPlatform $platform): bool => $platform->socialAccount !== null
                    && $platform->socialAccount->userHasAccess($request->user(), 'can_access_content')),
                403,
                'Bạn chưa được cấp quyền đăng bài cho một trong các Page đã chọn.',
            );
        }

        $result = UpdatePost::execute($workspace, $post, $validated);

        $action = data_get($result, 'action');

        if ($action === PostAction::Finalized) {
            session()->flash('flash.banner', __('posts.flash.cannot_edit_finalized'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        if ($action === PostAction::Publishing) {
            return redirect()->route('app.posts.show', $post);
        }

        if ($action === PostAction::Scheduled) {
            session()->flash('flash.banner', __('posts.flash.scheduled'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.posts.show', $post);
        }

        if ($post->wasChanged('status') && $post->status === PostStatus::Draft) {
            session()->flash('flash.banner', __('posts.flash.unscheduled'));
            session()->flash('flash.bannerStyle', 'success');
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('delete', $post);

        if (PostStatusRules::blocksDeletion($post)) {
            session()->flash('flash.banner', __('posts.flash.cannot_delete_published'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        DeletePost::execute($post);

        session()->flash('flash.banner', __('posts.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        $allowedRedirects = ['app.posts.index', 'app.calendar'];

        if ($redirect = $request->input('redirect')) {
            if (in_array($redirect, $allowedRedirects)) {
                return redirect()->route($redirect);
            }
        }

        return redirect()->route('app.posts.index');
    }

    public function duplicate(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('duplicate', $post);

        $post->load(['postPlatforms', 'labels']);

        $copy = DuplicatePost::execute($post, $request->user());

        session()->flash('flash.banner', __('posts.flash.duplicated'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.posts.edit', $copy);
    }
}
