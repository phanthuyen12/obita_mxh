<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Folder;

use App\Enums\Folder\Permission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignFolderPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePermissions', $this->route('folder'));
    }

    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*.user_id' => ['nullable', 'uuid', Rule::exists((new User)->getTable(), 'id')],
            'permissions.*.team_id' => [
                'nullable',
                'uuid',
                Rule::exists((new Team)->getTable(), 'id')->where(
                    fn ($query) => $query->where('workspace_id', $this->user()->current_workspace_id)
                ),
            ],
            'permissions.*.permission' => ['required', Rule::enum(Permission::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $workspace = $this->user()->currentWorkspace;

            foreach ((array) $this->input('permissions', []) as $index => $permission) {
                $hasUser = filled(data_get($permission, 'user_id'));
                $hasTeam = filled(data_get($permission, 'team_id'));

                if ($hasUser === $hasTeam) {
                    $validator->errors()->add("permissions.{$index}", 'Choose exactly one user or team.');
                }

                if ($hasUser && $workspace !== null) {
                    $userId = data_get($permission, 'user_id');
                    $isWorkspaceUser = $workspace->members()->whereKey($userId)->exists()
                        || $workspace->account?->owner_id === $userId;

                    if (! $isWorkspaceUser) {
                        $validator->errors()->add("permissions.{$index}.user_id", 'The user does not belong to this workspace.');
                    }
                }
            }
        }];
    }
}
