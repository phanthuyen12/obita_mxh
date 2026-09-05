<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'platform_user_id' => 'page-1',
        'access_token' => 'token',
    ]);
});

test('facebook post sync imports posts and is idempotent', function (): void {
    Http::fake([
        'https://graph.facebook.com/*/page-1/feed*' => Http::response([
            'data' => [[
                'id' => 'page-1_100',
                'message' => 'Bài cũ trên Facebook',
                'created_time' => '2026-08-12T01:00:00+0000',
                'permalink_url' => 'https://facebook.com/page-1/posts/100',
                'full_picture' => 'https://example.com/facebook-post.jpg',
                'shares' => ['count' => 12],
                'comments' => ['summary' => ['total_count' => 8]],
                'reactions' => ['summary' => ['total_count' => 21]],
            ]],
        ]),
    ]);

    $this->actingAs($this->user)->post(route('app.post-analytics.facebook.sync', $this->account))->assertRedirect();
    $this->actingAs($this->user)->post(route('app.post-analytics.facebook.sync', $this->account))->assertRedirect();

    expect($this->account->postPlatforms()->count())->toBe(1)
        ->and($this->account->postPlatforms()->first()->platform_post_id)->toBe('page-1_100')
        ->and($this->account->postPlatforms()->first()->post->media[0]['url'])->toBe('https://example.com/facebook-post.jpg')
        ->and($this->account->postPlatforms()->first()->snapshots()->first()->metrics)->toHaveCount(5);
});
