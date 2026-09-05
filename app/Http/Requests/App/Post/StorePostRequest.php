<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'folder_id' => ['nullable', 'uuid', Rule::exists('folders', 'id')->where('workspace_id', $this->user()?->current_workspace_id)],
            'media' => ['nullable', 'array'],
            'topic_tags' => ['nullable', 'array', 'max:20'],
            'topic_tags.*' => ['string', 'max:100', 'distinct:ignore_case'],
            'content_workflow_id' => [
                'nullable',
                'uuid',
                Rule::exists('content_workflows', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $this->user()?->currentWorkspace?->id)
                ),
            ],
        ];
    }
}
