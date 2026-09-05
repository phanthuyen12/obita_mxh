<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Enums\Folder\Permission;
use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('update', $this->route('post'))) {
            return false;
        }

        $folderId = $this->input('folder_id');

        if ($folderId === null) {
            return $this->user()->can('manageTeam', $this->user()->currentWorkspace);
        }

        $folder = Folder::query()->find($folderId);

        return $folder?->userHasPermission($this->user(), Permission::View) ?? false;
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
