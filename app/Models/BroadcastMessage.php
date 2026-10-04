<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'broadcast_campaign_id',
        'contact_id',
        'conversation_id',
        'sent_body',
        'image_url',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'broadcast_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OmnichatContact::class, 'contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OmnichatConversation::class, 'conversation_id');
    }
}
