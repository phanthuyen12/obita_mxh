<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatTagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OmnichatTag extends Model
{
    /** @use HasFactory<OmnichatTagFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'color' => '#64748B',
    ];

    protected $fillable = ['workspace_id', 'name', 'color'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            OmnichatConversation::class,
            'omnichat_conversation_tag',
            'omnichat_tag_id',
            'omnichat_conversation_id',
        )->withTimestamps();
    }
}
