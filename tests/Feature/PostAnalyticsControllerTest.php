<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('post analytics requires authentication', function (): void {
    $this->get(route('app.post-analytics.index'))->assertRedirect(route('login'));
});

test('post analytics lists published posts for current workspace', function (): void {
    Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
        'content' => 'Bài viết xu hướng',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.post-analytics.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Posts')
            ->has('posts.data', 1)
            ->where('posts.data.0.content', 'Bài viết xu hướng')
            ->where('summary.posts', 1)
            ->where('filters.period', '30d')
            ->has('comparison.views')
            ->has('comparison.interactions')
            ->has('dateRange.from')
            ->has('dateRange.previous_from')
            ->has('facebookPages')
        );
});

test('post analytics accepts a custom comparison range', function (): void {
    $this->actingAs($this->user)
        ->get(route('app.post-analytics.index', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-07',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('filters.period', 'custom')
            ->where('dateRange.days', 7)
            ->where('dateRange.from', fn (string $value): bool => str_starts_with($value, '2026-08-01'))
            ->where('dateRange.to', fn (string $value): bool => str_starts_with($value, '2026-08-07'))
        );
});

test('post analytics rejects custom ranges longer than one year', function (): void {
    $this->actingAs($this->user)
        ->get(route('app.post-analytics.index', [
            'period' => 'custom',
            'from' => '2025-01-01',
            'to' => '2026-08-07',
        ]))
        ->assertSessionHasErrors('to');
});

test('post analytics detail is limited to the post workspace', function (): void {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    $this->actingAs($this->user)
        ->get(route('app.post-analytics.show', $post))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('analytics/Post')->has('post')->has('platforms'));
});
