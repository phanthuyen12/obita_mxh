<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmnichatConversation extends Model
{
    /** @use HasFactory<OmnichatConversationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'social_account_id',
        'channel_id',
        'contact_id',
        'external_id',
        'status',
        'priority',
        'assigned_user_id',
        'last_message_preview',
        'last_message_at',
        'last_inbound_at',
        'last_outbound_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OmnichatContact::class, 'contact_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(OmnichatChannel::class, 'channel_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OmnichatMessage::class, 'conversation_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            OmnichatTag::class,
            'omnichat_conversation_tag',
            'omnichat_conversation_id',
            'omnichat_tag_id',
        )->withTimestamps();
    }
}
