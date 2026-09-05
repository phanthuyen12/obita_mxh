<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Folder\Permission;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderPermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'user_id',
        'team_id',
        'permission',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return ['permission' => Permission::class];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
