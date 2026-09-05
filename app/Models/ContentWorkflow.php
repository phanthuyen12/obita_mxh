<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContentWorkflowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentWorkflow extends Model
{
    /** @use HasFactory<ContentWorkflowFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['workspace_id', 'name', 'description', 'social_account_id', 'social_account_ids', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'social_account_ids' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'content_workflow_members')
            ->using(ContentWorkflowMember::class)
            ->withPivot(['can_write', 'can_review', 'can_publish'])
            ->withTimestamps();
    }
}
