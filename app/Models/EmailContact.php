<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailCampaign\ContactStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailContact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'email_contact_list_id',
        'email',
        'name',
        'custom_fields',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'custom_fields' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(EmailContactList::class, 'email_contact_list_id');
    }
}
