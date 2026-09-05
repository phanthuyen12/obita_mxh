<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('posts')
            ->whereNotNull('media')
            ->orderBy('id')
            ->get(['id', 'media'])
            ->each(function (object $post): void {
                $media = json_decode((string) $post->media, true);

                if (! is_array($media)) {
                    return;
                }

                $normalizedMedia = $this->normalizeValue($media);

                if ($normalizedMedia !== $media) {
                    DB::table('posts')
                        ->where('id', $post->id)
                        ->update(['media' => json_encode($normalizedMedia, JSON_THROW_ON_ERROR)]);
                }
            });

        DB::table('social_accounts')
            ->whereNotNull('avatar_url')
            ->orderBy('id')
            ->get(['id', 'avatar_url'])
            ->each(function (object $account): void {
                $avatarUrl = $this->normalizeValue($account->avatar_url);

                if ($avatarUrl !== $account->avatar_url) {
                    DB::table('social_accounts')
                        ->where('id', $account->id)
                        ->update(['avatar_url' => $avatarUrl]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // URL normalization is intentionally not reversible.
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
        }

        if (! is_string($value) || ! Str::contains($value, '/storage/')) {
            return $value;
        }

        return '/storage/'.ltrim(Str::after($value, '/storage/'), '/');
    }
};
