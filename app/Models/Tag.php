<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['workspace_id', 'name', 'slug', 'color', 'created_by'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable')->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphedByMany(Media::class, 'taggable')->withTimestamps();
    }

    public static function ensureDefaultTags(Workspace $workspace): void
    {
        $defaults = [
            ['name' => 'CEO', 'color' => '#f59e0b'],
            ['name' => 'Thương hiệu', 'color' => '#6366f1'],
            ['name' => 'Sản phẩm', 'color' => '#10b981'],
            ['name' => 'Khuyến mãi', 'color' => '#ec4899'],
            ['name' => 'Sự kiện', 'color' => '#8b5cf6'],
        ];

        foreach ($defaults as $default) {
            $workspace->tags()->firstOrCreate(
                ['slug' => str($default['name'])->slug()->toString()],
                [...$default, 'created_by' => $workspace->user_id],
            );
        }
    }
}
