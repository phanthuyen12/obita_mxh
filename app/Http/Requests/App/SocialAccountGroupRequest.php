<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialAccountGroupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'social_account_ids' => $this->input('social_account_ids', []),
        ]);
    }

    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null
            && $this->user()->can('manageAccounts', $workspace);
    }

    public function rules(): array
    {
        $workspace = $this->user()->currentWorkspace;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('social_account_groups')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($this->route('group')),
            ],
            'social_account_ids' => ['present', 'array'],
            'social_account_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('social_accounts', 'id')
                    ->where('workspace_id', $workspace->id),
            ],
        ];
    }
}
