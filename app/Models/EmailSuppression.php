<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailCampaign\SuppressionReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSuppression extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'source_campaign_id',
        'email',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'reason' => SuppressionReason::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourceCampaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'source_campaign_id');
    }
}
