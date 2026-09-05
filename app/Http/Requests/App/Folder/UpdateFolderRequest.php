<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Folder;

use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists((new Folder)->getTable(), 'id')->where(
                    fn ($query) => $query->where('workspace_id', $this->user()->current_workspace_id)->whereNull('deleted_at')
                ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_locked' => ['sometimes', 'boolean'],
            'is_shared_with_workspace' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Folder $folder */
            $folder = $this->route('folder');

            if (! $this->has('parent_id')) {
                return;
            }

            if ($folder->isMaster() && $this->input('parent_id') !== null) {
                $validator->errors()->add('parent_id', 'A master folder cannot have a parent.');

                return;
            }

            if (! $folder->isMaster() && $this->input('parent_id') === null) {
                $validator->errors()->add('parent_id', 'A personal folder must remain inside another folder.');

                return;
            }

            $parent = Folder::query()->find($this->input('parent_id'));

            if ($parent !== null && ($parent->is($folder) || $parent->isDescendantOf($folder))) {
                $validator->errors()->add('parent_id', 'A folder cannot be moved into itself or one of its descendants.');
            }
        }];
    }
}
