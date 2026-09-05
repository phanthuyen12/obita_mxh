<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Notification;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $from = $request->input('from');
        $to = $request->input('to');

        $query = $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->when($from, fn ($q) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));

        $notifications = (clone $query)->latest()->limit(50)->get();

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        if (! $request->expectsJson()) {
            $posts = Post::query()
                ->with(['postPlatforms.socialAccount', 'user'])
                ->whereIn('id', $notifications->pluck('data')->pluck('post_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $tasks = $notifications->map(function (Notification $notification) use ($posts): ?array {
                $post = $posts->get(data_get($notification->data, 'post_id'));
                if (! $post) {
                    return null;
                }

                $platform = $post->postPlatforms->firstWhere('enabled', true);
                $media = collect($post->media ?? [])->first();

                return [
                    'id' => $notification->id,
                    'title' => Str::limit(trim((string) $post->content) ?: 'Bài viết chưa có nội dung', 85),
                    'excerpt' => Str::limit(trim((string) $post->content) ?: 'Chưa có nội dung', 120),
                    'thumbnail' => data_get($media, 'url') ?? data_get($media, 'path'),
                    'platform' => $platform?->socialAccount?->platform?->value,
                    'platform_name' => $platform?->socialAccount?->display_name,
                    'author' => $post->user?->name,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'post_id' => $post->id,
                    'role' => data_get($notification->data, 'role'),
                    'workflow_status' => $post->workflow_status,
                    'workflow_note' => $post->workflow_note,
                    'post_status' => $post->status->value,
                ];
            })->filter()->values();

            $uniqueTasks = $tasks
                ->unique(fn (array $task): string => $task['post_id'].'-'.$task['role'])
                ->values();

            return Inertia::render('notifications/Index', [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
                'tasks' => $uniqueTasks,
                'filters' => [
                    'from' => $from ?? '',
                    'to' => $to ?? '',
                ],
                'stats' => [
                    'pending_review' => $uniqueTasks->where('workflow_status', 'pending_review')->where('role', 'review')->count(),
                    'editing' => $uniqueTasks->where('workflow_status', 'rejected')->count(),
                    'approved' => $uniqueTasks->where('workflow_status', 'approved')->count(),
                    'rejected' => $uniqueTasks->where('workflow_status', 'rejected')->count(),
                    'published' => $uniqueTasks
                        ->whereIn('post_status', ['published', 'partially_published'])
                        ->count(),
                ],
            ]);
        }

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $notification->markAsRead();

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return response()->json(['success' => true]);
        }

        $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }

    public function archiveAll(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return response()->json(['success' => true]);
        }

        $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return response()->json(['success' => true]);
    }
}
