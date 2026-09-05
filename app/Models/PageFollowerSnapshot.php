<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageFollowerSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'social_account_id',
        'follower_count',
        'date',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'follower_count' => 'integer',
            'date' => 'date',
            'captured_at' => 'datetime',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
