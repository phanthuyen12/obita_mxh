<?php

declare(strict_types=1);

use App\Enums\Notification\Type as NotificationType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\SendNotification;
use App\Mail\PostWorkflowApproved;
use App\Mail\PostWorkflowRejected;
use App\Models\ContentWorkflow;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

test('approving post workflow sends notification and email to post author', function (): void {
    $author = User::factory()->create();
    $reviewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $reviewer->id]);
    $workspace->members()->attach($author, ['role' => Role::Member->value]);
    $workspace->members()->attach($reviewer, ['role' => Role::Admin->value]);

    $reviewer->update(['current_workspace_id' => $workspace->id]);
    $author->update(['current_workspace_id' => $workspace->id]);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Facebook,
        'is_active' => true,
    ]);

    $workflow = ContentWorkflow::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Default Workflow',
        'is_active' => true,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $author->id,
        'content_workflow_id' => $workflow->id,
        'workflow_status' => 'pending_review',
        'scheduled_at' => now()->addDay(),
        'status' => PostStatus::Draft,
    ]);

    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $this->actingAs($reviewer)
        ->post(route('app.posts.workflow.approve', $post), [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect();

    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($author): bool {
        return $job->user->id === $author->id
            && $job->type === NotificationType::PostApproved
            && $job->mailable instanceof PostWorkflowApproved;
    });
});

test('rejecting post workflow sends notification and email to post author with reason', function (): void {
    $author = User::factory()->create();
    $reviewer = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $reviewer->id]);
    $workspace->members()->attach($author, ['role' => Role::Member->value]);
    $workspace->members()->attach($reviewer, ['role' => Role::Admin->value]);

    $reviewer->update(['current_workspace_id' => $workspace->id]);
    $author->update(['current_workspace_id' => $workspace->id]);

    $workflow = ContentWorkflow::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Default Workflow',
        'is_active' => true,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $author->id,
        'content_workflow_id' => $workflow->id,
        'workflow_status' => 'pending_review',
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($reviewer)
        ->post(route('app.posts.workflow.reject', $post), [
            'note' => 'Vui lòng bổ sung thêm hình ảnh sản phẩm.',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($author): bool {
        return $job->user->id === $author->id
            && $job->type === NotificationType::PostRejected
            && $job->mailable instanceof PostWorkflowRejected;
    });
});
