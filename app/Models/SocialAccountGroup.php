<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SocialAccountGroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SocialAccountGroup extends Model
{
    /** @use HasFactory<SocialAccountGroupFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialAccount::class,
            'social_account_group_members',
        );
    }
}
