<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmnichatContactIdentityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichatContactIdentity extends Model
{
    /** @use HasFactory<OmnichatContactIdentityFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'social_account_id',
        'channel_id',
        'provider',
        'external_id',
        'display_name',
        'avatar_url',
        'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
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
}
