<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Folder\Permission;
use App\Models\Folder;
use App\Models\Media;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Media $media */
        $media = $this->route('media');
        $workspace = $this->user()->currentWorkspace;

        if ($workspace === null
            || $media->mediable_type !== $workspace->getMorphClass()
            || $media->mediable_id !== $workspace->id) {
            return false;
        }

        if ($media->folder_id === null) {
            return $this->user()->can('createPost', $workspace);
        }

        $folder = Folder::query()->find($media->folder_id);

        return $folder !== null && $folder->userHasPermission($this->user(), Permission::EditMedia);
    }

    public function rules(): array
    {
        return [
            'tags' => ['present', 'array', 'max:20'],
            'tags.*' => [
                'required',
                'string',
                'max:50',
                'distinct:ignore_case',
                Rule::exists((new Tag)->getTable(), 'name')->where('workspace_id', $this->user()->current_workspace_id),
            ],
        ];
    }
}
