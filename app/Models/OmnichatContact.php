<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmnichatContact extends Model
{
    /** @use HasFactory<OmnichatContactFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'is_lead' => false,
        'lead_stage' => 'new',
    ];

    protected $fillable = [
        'workspace_id',
        'display_name',
        'avatar_url',
        'email',
        'phone',
        'notes',
        'status',
        'locale',
        'meta',
        'last_seen_at',
        'is_lead',
        'lead_stage',
        'phone_detected_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_seen_at' => 'datetime',
            'is_lead' => 'boolean',
            'phone_detected_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(OmnichatContactIdentity::class, 'contact_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(OmnichatConversation::class, 'contact_id');
    }
}
