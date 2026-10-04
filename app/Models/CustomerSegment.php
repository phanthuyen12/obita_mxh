<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSegment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'description',
        'rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function broadcastCampaigns(): HasMany
    {
        return $this->hasMany(BroadcastCampaign::class);
    }

    /**
     * Scope to filter Omnichat contacts based on this segment's rules.
     *
     * Rules schema:
     * - has_phone: bool
     * - inactive_days: int (e.g. 30, 40)
     * - lead_status: string
     * - is_lead: bool
     */
    public function filterContacts(Builder $query): Builder
    {
        $rules = $this->rules ?? [];

        if (! empty($rules['has_phone'])) {
            $query->where(function (Builder $q): void {
                $q->whereNotNull('phone')->where('phone', '!=', '');
            });
        }

        if (isset($rules['has_phone']) && $rules['has_phone'] === false) {
            $query->where(function (Builder $q): void {
                $q->whereNull('phone')->orWhere('phone', '');
            });
        }

        if (! empty($rules['inactive_days'])) {
            $days = (int) $rules['inactive_days'];
            $threshold = now()->subDays($days);
            $query->where(function (Builder $q) use ($threshold): void {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<=', $threshold);
            });
        }

        if (! empty($rules['is_lead'])) {
            $query->where('is_lead', true);
        }

        if (! empty($rules['lead_status'])) {
            $query->where('lead_status', $rules['lead_status']);
        }

        return $query;
    }
}
