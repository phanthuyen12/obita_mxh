<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceWebhook extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WorkspaceWebhookDelivery::class, 'workspace_webhook_id');
    }

    public function hasEvent(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
