<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContentWorkflowMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ContentWorkflowMember extends Pivot
{
    /** @use HasFactory<ContentWorkflowMemberFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $table = 'content_workflow_members';

    protected $fillable = ['id', 'content_workflow_id', 'user_id', 'can_write', 'can_review', 'can_publish'];

    protected function casts(): array
    {
        return ['can_write' => 'boolean', 'can_review' => 'boolean', 'can_publish' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ContentWorkflow::class, 'content_workflow_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
