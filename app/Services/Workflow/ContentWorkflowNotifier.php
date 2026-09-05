<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Support\Str;

class ContentWorkflowNotifier
{
    public function notifyReviewers(Post $post): void
    {
        $post->loadMissing('contentWorkflow.members');
        $workflow = $post->contentWorkflow;

        if (! $workflow || blank(trim((string) $post->content))) {
            return;
        }

        $excerpt = Str::limit(trim((string) $post->content), 90);

        foreach ($workflow->members as $member) {
            if (! $member->pivot->can_review) {
                continue;
            }

            $alreadyNotified = Notification::query()
                ->where('user_id', $member->id)
                ->where('type', Type::PostReady)
                ->whereJsonContains('data->post_id', $post->id)
                ->whereJsonContains('data->role', 'review')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            Notification::create([
                'user_id' => $member->id,
                'workspace_id' => $post->workspace_id,
                'type' => Type::PostReady,
                'channel' => Channel::InApp,
                'title' => 'Có bài viết cần duyệt',
                'body' => "Bài viết “{$excerpt}” trong luồng {$workflow->name} đang chờ bạn duyệt.",
                'data' => [
                    'post_id' => $post->id,
                    'workflow_id' => $workflow->id,
                    'role' => 'review',
                ],
            ]);
        }
    }
}
