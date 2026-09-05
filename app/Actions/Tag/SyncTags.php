<?php

declare(strict_types=1);

namespace App\Actions\Tag;

use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SyncTags
{
    /** @param array<int, mixed> $names */
    public static function execute(Workspace $workspace, Model $taggable, array $names, bool $detachMissing = true): void
    {
        $normalizedNames = collect($names)
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values();

        $existing = $workspace->tags()->get()->keyBy(fn (Tag $tag): string => mb_strtolower($tag->name));
        $tagIds = $normalizedNames->map(function (string $name) use ($workspace, $existing): string {
            $key = mb_strtolower($name);
            if ($existing->has($key)) {
                return $existing->get($key)->id;
            }

            $baseSlug = Str::slug($name) ?: 'tag';
            $slug = $baseSlug;
            $suffix = 2;
            while ($workspace->tags()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $tag = $workspace->tags()->create([
                'name' => $name,
                'slug' => $slug,
                'color' => '#6366f1',
                'created_by' => Auth::id(),
            ]);
            $existing->put($key, $tag);

            return $tag->id;
        })->all();

        /** @var MorphToMany<Tag, Model> $relation */
        $relation = $taggable->tags();
        $detachMissing ? $relation->sync($tagIds) : $relation->syncWithoutDetaching($tagIds);
    }
}
