<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'email_contact_list_id',
        'created_by_user_id',
        'name',
        'subject',
        'preview_text',
        'body_html',
        'from_name',
        'reply_to',
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
            'status' => Status::class,
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

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(EmailContactList::class, 'email_contact_list_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    /**
     * Recompute the cached counters from the recipient rows.
     *
     * @return array{total: int, queued: int, sent: int, delivered: int, opened: int, clicked: int, bounced: int, failed: int, skipped: int}
     */
    public function recalculateStats(): array
    {
        $counts = $this->recipients()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCount = fn (RecipientStatus $status): int => (int) $counts->get($status->value, 0);

        return [
            'total' => (int) $counts->sum(),
            'queued' => $statusCount(RecipientStatus::Queued),
            'sent' => $statusCount(RecipientStatus::Sent),
            'delivered' => $statusCount(RecipientStatus::Delivered),
            'opened' => $statusCount(RecipientStatus::Opened),
            'clicked' => $statusCount(RecipientStatus::Clicked),
            'bounced' => $statusCount(RecipientStatus::Bounced),
            'failed' => $statusCount(RecipientStatus::Failed),
            'skipped' => $statusCount(RecipientStatus::Skipped),
        ];
    }
}
