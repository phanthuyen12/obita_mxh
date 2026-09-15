<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceWebhookDelivery extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_webhook_id',
        'event',
        'payload',
        'response_status',
        'response_body',
        'duration_ms',
        'status',
        'attempts',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(WorkspaceWebhook::class, 'workspace_webhook_id');
    }
}
