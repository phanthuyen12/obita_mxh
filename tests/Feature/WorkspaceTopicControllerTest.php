<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('post tags index requires authentication', function () {
    $response = $this->get(route('app.post-tags.index'));

    $response->assertRedirect(route('login'));
});

test('post tags index shows tags and ensures workspace defaults', function () {
    $response = $this->actingAs($this->user)->get(route('app.post-tags.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('topics/Index', false)
        ->has('workspace')
        ->has('tags.data')
    );

    $this->assertDatabaseHas('tags', [
        'workspace_id' => $this->workspace->id,
        'name' => 'CEO',
    ]);
});

test('store creates a post tag', function () {
    $response = $this->actingAs($this->user)->post(route('app.post-tags.store'), [
        'name' => 'Tầm nhìn 2026',
        'color' => '#10b981',
    ]);

    $response->assertRedirect(route('app.post-tags.index'));

    $this->assertDatabaseHas('tags', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Tầm nhìn 2026',
        'color' => '#10b981',
    ]);
});

test('update changes the shared post tag', function () {
    $topic = Tag::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Tên cũ',
        'slug' => 'ten-cu',
        'color' => '#6366f1',
    ]);

    $response = $this->actingAs($this->user)->put(route('app.post-tags.update', $topic), [
        'name' => 'Tên mới',
        'color' => '#f59e0b',
    ]);

    $response->assertRedirect(route('app.post-tags.index'));

    $topic->refresh();
    expect($topic->name)->toBe('Tên mới');
    expect($topic->color)->toBe('#f59e0b');
});

test('destroy deletes the post tag', function () {
    $topic = Tag::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Topic test',
        'slug' => 'topic-test',
        'color' => '#6366f1',
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.post-tags.destroy', $topic));

    $response->assertRedirect(route('app.post-tags.index'));
    expect(Tag::find($topic->id))->toBeNull();
});
