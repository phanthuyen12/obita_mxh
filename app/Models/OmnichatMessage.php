<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichatMessage extends Model
{
    /** @use HasFactory<OmnichatMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'social_account_id',
        'channel_id',
        'conversation_id',
        'sender_contact_id',
        'sender_user_id',
        'client_id',
        'external_id',
        'direction',
        'type',
        'body',
        'status',
        'provider_payload',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OmnichatConversation::class, 'conversation_id');
    }

    public function senderContact(): BelongsTo
    {
        return $this->belongsTo(OmnichatContact::class, 'sender_contact_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(OmnichatChannel::class, 'channel_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
