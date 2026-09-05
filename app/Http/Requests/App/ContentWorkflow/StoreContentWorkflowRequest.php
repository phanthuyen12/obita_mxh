<?php

declare(strict_types=1);

namespace App\Http\Requests\App\ContentWorkflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeam', $this->user()->currentWorkspace) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'social_account_id' => ['nullable', 'uuid'],
            'social_account_ids' => ['nullable', 'array'],
            'social_account_ids.*' => ['required', 'uuid', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
            'members' => ['sometimes', 'array'],
            'members.*.user_id' => ['required', 'uuid', 'distinct'],
            'members.*.can_write' => ['sometimes', 'boolean'],
            'members.*.can_review' => ['sometimes', 'boolean'],
            'members.*.can_publish' => ['sometimes', 'boolean'],
        ];
    }
}
