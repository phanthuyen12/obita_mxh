<?php

namespace App\Actions\Post;

use App\Actions\Tag\SyncTags;
use App\Models\Media;
use App\Models\Workspace;

class SyncPostMediaTags
{
    /** @param array<int, mixed> $mediaItems @param array<int, mixed> $topicTags */
    public static function execute(Workspace $workspace, array $mediaItems, array $topicTags): void
    {
        $mediaIds = collect($mediaItems)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();
        $tags = collect($topicTags)
            ->filter(fn (mixed $tag): bool => is_string($tag) && trim($tag) !== '')
            ->map(fn (string $tag): string => trim($tag))
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values();

        if ($mediaIds->isEmpty() || $tags->isEmpty()) {
            return;
        }

        Media::query()
            ->where('mediable_type', $workspace->getMorphClass())
            ->where('mediable_id', $workspace->id)
            ->whereIn('id', $mediaIds)
            ->each(function (Media $media) use ($workspace, $tags): void {
                SyncTags::execute($workspace, $media, $tags->all(), detachMissing: false);
            });
    }
}
