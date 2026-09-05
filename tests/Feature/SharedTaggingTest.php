<?php

declare(strict_types=1);

use App\Actions\Tag\SyncTags;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('post and media share the same workspace tag', function () {
    Storage::fake();
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $media = $workspace->addMedia(UploadedFile::fake()->image('campaign.jpg'), 'assets');

    SyncTags::execute($workspace, $post, ['Marketing']);
    SyncTags::execute($workspace, $media, ['Marketing']);

    $tag = Tag::query()->where('workspace_id', $workspace->id)->where('slug', 'marketing')->sole();

    expect($post->tags()->sole()->is($tag))->toBeTrue()
        ->and($media->tags()->sole()->is($tag))->toBeTrue()
        ->and($tag->posts()->sole()->is($post))->toBeTrue()
        ->and($tag->media()->sole()->is($media))->toBeTrue()
        ->and(Media::query()->whereHas('tags', fn ($query) => $query->whereKey($tag->id))->sole()->is($media))->toBeTrue();

    $tag->update(['name' => 'Tiếp thị', 'slug' => 'tiep-thi']);

    expect($post->fresh()->topic_tags)->toBe(['Tiếp thị'])
        ->and($media->fresh()->tags()->pluck('name')->all())->toBe(['Tiếp thị']);
});
