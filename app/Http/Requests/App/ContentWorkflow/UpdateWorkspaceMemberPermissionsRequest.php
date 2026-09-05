<?php

declare(strict_types=1);

namespace App\Http\Requests\App\ContentWorkflow;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;
        $member = $this->route('member');

        return $workspace !== null
            && $member instanceof User
            && $this->user()->can('manageTeam', $workspace)
            && $workspace->members()->whereKey($member->id)->exists();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'can_omnichat' => ['required', 'boolean'],
            'can_content' => ['required', 'boolean'],
            'omnichat_social_account_ids' => ['present', 'array'],
            'omnichat_social_account_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('social_accounts', 'id')->where('workspace_id', $this->user()->current_workspace_id),
            ],
            'content_social_account_ids' => ['present', 'array'],
            'content_social_account_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('social_accounts', 'id')->where('workspace_id', $this->user()->current_workspace_id),
            ],
        ];
    }
}
