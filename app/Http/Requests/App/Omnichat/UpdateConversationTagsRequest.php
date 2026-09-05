<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('view', $workspace);
    }

    public function rules(): array
    {
        return [
            'tag_ids' => ['present', 'array', 'max:20'],
            'tag_ids.*' => [
                'uuid',
                Rule::exists('omnichat_tags', 'id')->where('workspace_id', $this->user()?->current_workspace_id),
            ],
        ];
    }
}
