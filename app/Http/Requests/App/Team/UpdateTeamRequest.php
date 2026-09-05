<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Team;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;
        $team = $this->route('team');

        return $workspace !== null
            && $team instanceof Team
            && $team->workspace_id === $workspace->id
            && $this->user()->can('manageTeam', $workspace);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workspaceId = $this->user()->current_workspace_id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('teams')->where('workspace_id', $workspaceId)->ignore($this->route('team')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
