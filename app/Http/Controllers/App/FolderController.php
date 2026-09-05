<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Folder\Permission;
use App\Enums\Folder\Type;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Folder\AssignFolderPermissionsRequest;
use App\Http\Requests\App\Folder\StoreFolderRequest;
use App\Http\Requests\App\Folder\UpdateFolderRequest;
use App\Models\Folder;
use App\Models\FolderPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    public function manage(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        Gate::authorize('viewAny', Folder::class);

        $folders = $this->foldersAccessibleTo($request);

        return Inertia::render('folders/Index', [
            'folders' => $this->folderPayload($folders, $request),
            'canManageAllFolders' => $request->user()->can('manageTeam', $workspace),
            'permissionOptions' => collect(Permission::cases())
                ->map(fn (Permission $permission): array => [
                    'value' => $permission->value,
                    'label' => $permission->label(),
                ]),
        ]);
    }

    public function subjects(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        abort_unless($workspace !== null, 403);

        $type = $request->input('type', 'user');
        abort_unless(in_array($type, ['user', 'team'], true), 422);

        $search = trim((string) $request->input('search'));
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = $type === 'team'
            ? $workspace->teams()->where('is_active', true)
            : $workspace->members();

        $subjects = $query
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search, $likeOperator, $type): void {
                $searchQuery->where('name', $likeOperator, "%{$search}%");

                if ($type === 'user') {
                    $searchQuery->orWhere('email', $likeOperator, "%{$search}%");
                }
            }))
            ->orderBy('name')
            ->paginate(30, $type === 'team' ? ['id', 'name'] : ['users.id', 'name', 'email']);

        return response()->json($subjects);
    }

    public function permissions(Request $request, Folder $folder): JsonResponse
    {
        Gate::authorize('managePermissions', $folder);

        return response()->json([
            'data' => $folder->permissions()
                ->with(['user:id,name,email', 'team:id,name'])
                ->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Folder::class);

        $workspace = $request->user()->currentWorkspace;
        $canManageAll = $request->user()->can('manageTeam', $workspace);

        $folders = $canManageAll
            ? Folder::query()->forWorkspace($workspace)->with(['owner:id,name,email', 'createdBy:id,name,email'])->withCount(['children', 'medias', 'posts'])->orderBy('sort_order')->orderBy('name')->get()
            : $this->foldersAccessibleTo($request);

        return response()->json(['data' => $this->folderPayload($folders, $request)]);
    }

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $type = Type::from($request->validated('type'));
        $parent = $request->validated('parent_id')
            ? Folder::query()->findOrFail($request->validated('parent_id'))
            : null;

        $type === Type::Master
            ? Gate::authorize('createMaster', Folder::class)
            : Gate::authorize('createChild', $parent);

        $folder = Folder::query()->create([
            'workspace_id' => $request->user()->current_workspace_id,
            'parent_id' => $parent?->id,
            'master_folder_id' => $parent?->master_folder_id ?? $parent?->id,
            'name' => $request->validated('name'),
            'type' => $type,
            'created_by' => $request->user()->id,
            'owner_user_id' => $type === Type::Personal ? $request->user()->id : null,
            'sort_order' => $request->integer('sort_order'),
        ]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(UpdateFolderRequest $request, Folder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        $data = $request->safe()->only(['name', 'parent_id', 'sort_order', 'is_locked', 'is_shared_with_workspace']);

        if (array_key_exists('parent_id', $data) && ! $folder->isMaster()) {
            $parent = Folder::query()->findOrFail($data['parent_id']);
            Gate::authorize('createChild', $parent);
            $data['master_folder_id'] = $parent->master_folder_id ?? $parent->id;
        }

        if (array_key_exists('is_locked', $data) && ! $request->user()->can('manageTeam', $request->user()->currentWorkspace)) {
            unset($data['is_locked']);
        }

        DB::transaction(function () use ($folder, $data): void {
            $oldMasterFolderId = $folder->master_folder_id;
            $folder->update($data);

            if (array_key_exists('master_folder_id', $data) && $oldMasterFolderId !== $data['master_folder_id']) {
                Folder::query()
                    ->whereIn('id', $this->descendantIds($folder))
                    ->update(['master_folder_id' => $data['master_folder_id']]);
            }
        });

        return response()->json(['data' => $folder->fresh()]);
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        Gate::authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->medias()->exists() || $folder->posts()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Move or delete the folder contents before deleting this folder.',
            ]);
        }

        $folder->delete();

        return response()->json(status: 204);
    }

    public function assignPermissions(AssignFolderPermissionsRequest $request, Folder $folder): JsonResponse
    {
        DB::transaction(function () use ($request, $folder): void {
            $folder->permissions()->delete();

            $permissions = collect($request->validated('permissions'))->unique(
                fn (array $permission): string => implode(':', [
                    data_get($permission, 'user_id', ''),
                    data_get($permission, 'team_id', ''),
                    data_get($permission, 'permission'),
                ])
            );

            foreach ($permissions as $permission) {
                FolderPermission::query()->create([
                    ...$permission,
                    'folder_id' => $folder->id,
                    'assigned_by' => $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'data' => $folder->permissions()->with(['user:id,name,email', 'team:id,name'])->get(),
        ]);
    }

    private function foldersAccessibleTo(Request $request): Collection
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $folders = Folder::query()
            ->forWorkspace($workspace)
            ->with(['owner:id,name,email', 'createdBy:id,name,email'])
            ->withCount(['children', 'medias', 'posts'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($user->can('manageTeam', $workspace)) {
            return $folders;
        }

        $directFolderIds = FolderPermission::query()
            ->whereIn('folder_id', $folders->pluck('id'))
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('team.users', fn (Builder $members) => $members->whereKey($user->id));
            })
            ->pluck('folder_id')
            ->all();
        $accessibleIds = $folders
            ->filter(fn (Folder $folder): bool => $folder->owner_user_id === $user->id
                || $folder->is_shared_with_workspace
                || in_array($folder->id, $directFolderIds, true))
            ->pluck('id')
            ->all();

        do {
            $count = count($accessibleIds);
            foreach ($folders as $folder) {
                $parent = $folders->firstWhere('id', $folder->parent_id);

                if ($folder->parent_id !== null
                    && in_array($folder->parent_id, $accessibleIds, true)
                    && $parent !== null
                    && ! $parent->isMaster()) {
                    $accessibleIds[] = $folder->id;
                }
            }
            $accessibleIds = array_values(array_unique($accessibleIds));
        } while (count($accessibleIds) !== $count);

        return $folders->whereIn('id', $accessibleIds)->values();
    }

    private function folderPayload(Collection $folders, Request $request): Collection
    {
        return $folders->map(function (Folder $folder) use ($request): array {
            $responsibleUser = $folder->isMaster() ? null : ($folder->owner ?? $folder->createdBy);

            return [
                ...$folder->toArray(),
                'display_name' => $responsibleUser !== null
                    ? "{$folder->name} · {$responsibleUser->email}"
                    : $folder->name,
                'owner_name' => $responsibleUser?->name,
                'owner_email' => $responsibleUser?->email,
                'can' => [
                    'create' => Gate::allows('createChild', $folder),
                    'update' => Gate::allows('update', $folder),
                    'delete' => Gate::allows('delete', $folder),
                    'manage' => Gate::allows('managePermissions', $folder),
                    'view' => $folder->userHasPermission($request->user(), Permission::View),
                    'upload_media' => $folder->userHasPermission($request->user(), Permission::UploadMedia),
                    'edit_media' => $folder->userHasPermission($request->user(), Permission::EditMedia),
                    'delete_media' => $folder->userHasPermission($request->user(), Permission::DeleteMedia),
                ],
            ];
        });
    }

    /** @return list<string> */
    private function descendantIds(Folder $folder): array
    {
        $descendantIds = [];
        $pendingIds = [$folder->id];
        $visitedIds = [];

        while ($pendingIds !== []) {
            $pendingIds = array_values(array_diff($pendingIds, $visitedIds));

            if ($pendingIds === []) {
                break;
            }

            $visitedIds = [...$visitedIds, ...$pendingIds];
            $childIds = Folder::query()->whereIn('parent_id', $pendingIds)->pluck('id')->all();
            $descendantIds = [...$descendantIds, ...$childIds];
            $pendingIds = $childIds;
        }

        return $descendantIds;
    }
}
