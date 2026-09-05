<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentClonePreviewTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'status',
        'payload',
        'suggestions',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'suggestions' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
