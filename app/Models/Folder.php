<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Folder\Permission;
use App\Enums\Folder\Type;
use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'master_folder_id',
        'name',
        'type',
        'created_by',
        'owner_user_id',
        'is_locked',
        'is_shared_with_workspace',
        'sort_order',
    ];

    protected $attributes = [
        'type' => Type::Personal->value,
        'is_locked' => false,
        'is_shared_with_workspace' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => Type::class,
            'is_locked' => 'boolean',
            'is_shared_with_workspace' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function masterFolder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'master_folder_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(FolderPermission::class);
    }

    public function scopeMasterFolders(Builder $query): Builder
    {
        return $query->where('type', Type::Master);
    }

    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->whereBelongsTo($workspace);
    }

    public function isMaster(): bool
    {
        return $this->type === Type::Master;
    }

    public function isDescendantOf(self $folder): bool
    {
        $current = $this;
        $visited = [];

        while ($current->parent_id !== null && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;

            if ($current->parent_id === $folder->id) {
                return true;
            }

            $current = self::query()->find($current->parent_id);

            if ($current === null) {
                break;
            }
        }

        return false;
    }

    public function userHasPermission(User $user, Permission $permission): bool
    {
        if ($this->workspace_id !== $user->current_workspace_id) {
            return false;
        }

        $workspace = $user->currentWorkspace;

        if ($workspace !== null && $user->can('manageTeam', $workspace)) {
            return true;
        }

        if ($this->owner_user_id === $user->id) {
            return true;
        }

        if ($permission === Permission::View && $this->is_shared_with_workspace) {
            return true;
        }

        $folderIds = $this->permissionSourceIds();

        if ($permission === Permission::View
            && self::query()->whereIn('id', $folderIds)->where('is_shared_with_workspace', true)->exists()) {
            return true;
        }

        $permissions = $permission === Permission::View
            ? Permission::cases()
            : [$permission, Permission::ManageFolder];

        return FolderPermission::query()
            ->whereIn('folder_id', $folderIds)
            ->whereIn('permission', $permissions)
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('team.users', fn (Builder $members) => $members->whereKey($user->id));
            })
            ->exists();
    }

    /** @return list<string> */
    private function permissionSourceIds(): array
    {
        $folderIds = [$this->id];
        $parentId = $this->parent_id;
        $visited = [];

        while ($parentId !== null && ! isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parent = self::query()->find($parentId);

            if ($parent === null || $parent->isMaster()) {
                break;
            }

            $folderIds[] = $parent->id;
            $parentId = $parent->parent_id;
        }

        return $folderIds;
    }
}
