<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatWebchatSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichatWebchatSession extends Model
{
    /** @use HasFactory<OmnichatWebchatSessionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id', 'channel_id', 'conversation_id', 'token_hash', 'visitor_id_hash',
        'origin', 'locale', 'context', 'last_seen_at', 'expires_at', 'ended_at',
    ];

    protected $hidden = ['token_hash', 'visitor_id_hash'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(OmnichatChannel::class, 'channel_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OmnichatConversation::class, 'conversation_id');
    }
}
