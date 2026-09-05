<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Folder\Permission;
use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folderId = $this->input('folder_id');

        if ($folderId === null) {
            return $this->user()?->can('manageTeam', $this->user()->currentWorkspace) ?? false;
        }

        $folder = Folder::query()->find($folderId);

        return $folder?->userHasPermission($this->user(), Permission::EditMedia) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['folder_id' => [
            'nullable',
            'uuid',
            Rule::exists('folders', 'id')->where('workspace_id', $this->user()->current_workspace_id),
        ]];
    }
}
