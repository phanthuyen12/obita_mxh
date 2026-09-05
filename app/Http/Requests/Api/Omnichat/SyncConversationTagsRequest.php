<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Omnichat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncConversationTagsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
