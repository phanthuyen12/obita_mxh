<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostMetricSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMetricSnapshot extends Model
{
    /** @use HasFactory<PostMetricSnapshotFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['post_platform_id', 'metrics', 'captured_at'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'captured_at' => 'datetime'];
    }

    public function postPlatform(): BelongsTo
    {
        return $this->belongsTo(PostPlatform::class);
    }
}
