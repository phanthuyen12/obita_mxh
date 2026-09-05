<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('manageTeam', $workspace);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workspaceId = $this->user()->current_workspace_id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('teams')->where('workspace_id', $workspaceId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('user_workspace', 'user_id')->where('workspace_id', $workspaceId),
            ],
        ];
    }
}
