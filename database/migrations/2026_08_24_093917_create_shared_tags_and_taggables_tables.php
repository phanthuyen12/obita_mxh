<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('tag');
            $table->string('color', 7)->default('#6366f1');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->uuidMorphs('taggable');
            $table->timestamps();
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        $this->migrateExistingTags();

        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('topic_tags'));
        Schema::table('medias', fn (Blueprint $table) => $table->dropColumn('tags'));
        Schema::dropIfExists('workspace_topics');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('workspace_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('topic');
            $table->string('color')->default('#6366f1');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('posts', fn (Blueprint $table) => $table->json('topic_tags')->nullable());
        Schema::table('medias', fn (Blueprint $table) => $table->json('tags')->nullable());

        DB::table('tags')->orderBy('id')->each(function (object $tag): void {
            DB::table('workspace_topics')->insert([
                'id' => $tag->id,
                'workspace_id' => $tag->workspace_id,
                'name' => $tag->name,
                'type' => $tag->type,
                'color' => $tag->color,
                'created_at' => $tag->created_at,
                'updated_at' => $tag->updated_at,
                'deleted_at' => $tag->deleted_at,
            ]);
        });

        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }

    private function migrateExistingTags(): void
    {
        $tagIds = [];

        DB::table('workspace_topics')->whereNull('deleted_at')->lazyById(500)->each(
            function (object $topic) use (&$tagIds): void {
                $slug = $this->uniqueSlug($topic->workspace_id, $topic->name);
                DB::table('tags')->insert([
                    'id' => $topic->id,
                    'workspace_id' => $topic->workspace_id,
                    'name' => $topic->name,
                    'slug' => $slug,
                    'type' => $topic->type,
                    'color' => $topic->color,
                    'created_at' => $topic->created_at,
                    'updated_at' => $topic->updated_at,
                ]);
                $tagIds[$topic->workspace_id][mb_strtolower(trim($topic->name))] = $topic->id;
            },
        );

        foreach ([['posts', 'topic_tags', 'post'], ['medias', 'tags', 'media']] as [$table, $column, $type]) {
            DB::table($table)->whereNotNull($column)->lazyById(500)->each(
                function (object $record) use ($table, $column, $type, &$tagIds): void {
                    $workspaceId = $record->workspace_id ?? null;
                    if ($workspaceId === null && $table === 'medias' && $record->mediable_type === 'workspace') {
                        $workspaceId = $record->mediable_id;
                    }
                    if ($workspaceId === null) {
                        return;
                    }

                    foreach ($this->decodeNames($record->{$column}) as $name) {
                        $key = mb_strtolower($name);
                        $tagId = $tagIds[$workspaceId][$key] ?? null;
                        if ($tagId === null) {
                            $tagId = (string) Str::uuid();
                            DB::table('tags')->insert([
                                'id' => $tagId,
                                'workspace_id' => $workspaceId,
                                'name' => $name,
                                'slug' => $this->uniqueSlug($workspaceId, $name),
                                'type' => 'tag',
                                'color' => '#6366f1',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $tagIds[$workspaceId][$key] = $tagId;
                        }

                        DB::table('taggables')->insertOrIgnore([
                            'tag_id' => $tagId,
                            'taggable_id' => $record->id,
                            'taggable_type' => $type,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                },
            );
        }
    }

    /** @return array<int, string> */
    private function decodeNames(mixed $value): array
    {
        $values = is_string($value) ? json_decode($value, true) : $value;

        return collect(is_array($values) ? $values : [])
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }

    private function uniqueSlug(string $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $suffix = 2;
        while (DB::table('tags')->where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
};
