<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiBotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace-level Dify bot configuration. Multiple bots can be created; the
 * one flagged as default (and active) is used by the AI auto-reply listener
 * whenever a conversation's channel has no channel-specific API key.
 */
class AiBot extends Model
{
    /** @use HasFactory<AiBotFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'dify_api_key',
        'dify_base_url',
        'is_active',
        'is_default',
    ];

    protected $hidden = [
        'dify_api_key',
    ];

    protected function casts(): array
    {
        return [
            'dify_api_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The active default bot of a workspace, if any.
     */
    public static function defaultFor(string $workspaceId): ?self
    {
        return self::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
