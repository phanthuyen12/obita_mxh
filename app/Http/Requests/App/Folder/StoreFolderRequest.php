<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Folder;

use App\Enums\Folder\Type;
use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentWorkspace !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(Type::class)],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists((new Folder)->getTable(), 'id')->where(
                    fn ($query) => $query->where('workspace_id', $this->user()->current_workspace_id)->whereNull('deleted_at')
                ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = Type::tryFrom((string) $this->input('type'));

            if ($type === Type::Master && $this->filled('parent_id')) {
                $validator->errors()->add('parent_id', 'A master folder cannot have a parent.');
            }

            if ($type === Type::Personal && ! $this->filled('parent_id')) {
                $validator->errors()->add('parent_id', 'A personal folder must be created inside another folder.');
            }
        }];
    }
}
