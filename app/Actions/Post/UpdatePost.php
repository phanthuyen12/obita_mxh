<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Actions\Tag\SyncTags;
use App\Enums\Post\Action as PostAction;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\Workflow\ContentWorkflowNotifier;
use App\Support\PostStatusRules;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdatePost
{
    /**
     * @return array{post: Post, action: PostAction|null}
     */
    public static function execute(Workspace $workspace, Post $post, array $data): array
    {
        if (PostStatusRules::blocksEditing($post)) {
            return ['post' => $post, 'action' => PostAction::Finalized];
        }

        $status = data_get($data, 'status', $post->status);
        $statusValue = $status instanceof PostStatus ? $status->value : $status;

        $scheduledAt = null;
        if ($statusValue === PostStatus::Scheduled->value) {
            if (data_get($data, 'scheduled_at')) {
                $scheduledAt = Carbon::parse(data_get($data, 'scheduled_at'))->utc();
            } else {
                $scheduledAt = $post->scheduled_at;
            }
        } elseif ($statusValue === PostStatus::Publishing->value) {
            $scheduledAt = now();
        } else {
            // Status is Draft or other non-scheduled status
            if (Arr::has($data, 'scheduled_at') && data_get($data, 'scheduled_at')) {
                $scheduledAt = Carbon::parse(data_get($data, 'scheduled_at'))->utc();
            } else {
                $scheduledAt = null;
            }
        }

        $updatePayload = [
            'folder_id' => data_get($data, 'folder_id', $post->folder_id),
            'content' => data_get($data, 'content', $post->content),
            'media' => data_get($data, 'media', $post->media),
            'status' => $statusValue === PostStatus::Publishing->value ? PostStatus::Publishing : $status,
            'scheduled_at' => $scheduledAt,
        ];

        if ($statusValue === PostStatus::Draft->value && $post->workflow_status === 'approved') {
            $updatePayload['workflow_status'] = 'draft';
        }

        if (Arr::has($data, 'is_ceo_content')) {
            $updatePayload['is_ceo_content'] = (bool) data_get($data, 'is_ceo_content');
        }

        if (Arr::has($data, 'content_workflow_id')) {
            $contentWorkflowId = data_get($data, 'content_workflow_id');

            if ($contentWorkflowId !== $post->content_workflow_id) {
                $updatePayload['content_workflow_id'] = $contentWorkflowId;
                $updatePayload['workflow_status'] = 'draft';
                $updatePayload['workflow_note'] = null;
            }
        }

        $post->update($updatePayload);

        $resolvedTopicTags = Arr::has($data, 'topic_tags')
            ? (array) data_get($data, 'topic_tags', [])
            : $post->tags()->pluck('tags.name')->all();

        if (Arr::has($data, 'topic_tags')) {
            SyncTags::execute($workspace, $post, $resolvedTopicTags);
        }

        SyncPostMediaTags::execute(
            $workspace,
            (array) data_get($data, 'media', $post->media ?? []),
            $resolvedTopicTags,
        );

        $post->load('tags');

        app(ContentWorkflowNotifier::class)->notifyReviewers($post);

        if (Arr::has($data, 'label_ids')) {
            $post->labels()->sync(data_get($data, 'label_ids', []));
        }

        if (Arr::has($data, 'platforms')) {
            DB::transaction(function () use ($post, $data) {
                $post->postPlatforms()->update(['enabled' => false]);

                foreach (data_get($data, 'platforms', []) as $platformData) {
                    $updateData = ['enabled' => true];

                    if (data_get($platformData, 'content_type') !== null) {
                        $updateData['content_type'] = data_get($platformData, 'content_type');
                    }

                    if (data_get($platformData, 'meta') !== null) {
                        $postPlatform = $post->postPlatforms()->where('id', data_get($platformData, 'id'))->first();

                        if ($postPlatform) {
                            $updateData['meta'] = array_filter(
                                array_merge($postPlatform->meta ?? [], data_get($platformData, 'meta') ?? []),
                                fn (mixed $value): bool => $value !== null,
                            );
                        }
                    }

                    $post->postPlatforms()
                        ->where('id', data_get($platformData, 'id'))
                        ->update($updateData);
                }
            });
        }

        if ($status === PostStatus::Publishing->value) {
            $post->update(['scheduled_at' => now()]);
            PublishPost::dispatch($post);

            return ['post' => $post, 'action' => PostAction::Publishing];
        }

        if ($status === PostStatus::Scheduled->value) {
            return ['post' => $post, 'action' => PostAction::Scheduled];
        }

        return ['post' => $post, 'action' => null];
    }
}
