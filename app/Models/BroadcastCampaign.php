<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'customer_segment_id',
        'channel_id',
        'name',
        'trigger_type',
        'trigger_inactive_days',
        'message_template',
        'image_url',
        'ai_spin_enabled',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'delay_seconds',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'ai_spin_enabled' => 'boolean',
            'trigger_inactive_days' => 'integer',
            'delay_seconds' => 'integer',
            'stats' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function customerSegment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(OmnichatChannel::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class);
    }
}
