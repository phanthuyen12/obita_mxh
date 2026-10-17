<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailCampaign\RecipientStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignRecipient extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'email_campaign_id',
        'email_contact_id',
        'email',
        'name',
        'status',
        'provider_message_id',
        'error_message',
        'unsubscribe_token',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecipientStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(EmailContact::class, 'email_contact_id');
    }
}
