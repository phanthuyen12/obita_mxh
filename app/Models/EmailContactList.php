<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailCampaign\ContactStatus;
use App\Enums\EmailCampaign\ImportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailContactList extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'created_by_user_id',
        'name',
        'source_filename',
        'source_path',
        'total_rows',
        'imported_count',
        'invalid_count',
        'duplicate_count',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'imported_count' => 'integer',
            'invalid_count' => 'integer',
            'duplicate_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmailContact::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class);
    }

    public function activeContactsCount(): int
    {
        return $this->contacts()->where('status', ContactStatus::Active)->count();
    }
}
