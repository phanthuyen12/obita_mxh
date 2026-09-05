<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContentCloneCampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCloneCampaign extends Model
{
    /** @use HasFactory<ContentCloneCampaignFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'source_post_id',
        'content_workflow_id',
        'created_by',
        'target_social_account_ids',
        'theme',
        'prompt',
        'image_prompt',
        'ai_image_count',
        'ai_image_style',
        'ai_logo_path',
        'diff_content_per_page',
        'ai_image_resolution',
        'ai_image_aspect_ratio',
        'initial_content',
        'initial_media',
        'total_posts',
        'generated_posts',
        'interval_days',
        'start_at',
        'next_run_at',
        'require_approval',
        'is_active',
        'last_error',
        'ai_content_mode',
        'video_scenes',
    ];

    protected function casts(): array
    {
        return [
            'target_social_account_ids' => 'array',
            'initial_media' => 'array',
            'video_scenes' => 'array',
            'start_at' => 'datetime',
            'next_run_at' => 'datetime',
            'require_approval' => 'boolean',
            'is_active' => 'boolean',
            'ai_image_count' => 'integer',
            'diff_content_per_page' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'source_post_id');
    }

    public function contentWorkflow(): BelongsTo
    {
        return $this->belongsTo(ContentWorkflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->latest('scheduled_at');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereColumn('generated_posts', '<', 'total_posts')->where('next_run_at', '<=', now());
    }
}
