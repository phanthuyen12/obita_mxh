<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserWorkspace\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialAccountAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('share', $this->route('account')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => [
                'uuid',
                Rule::exists('user_workspace', 'user_id')
                    ->where('role', Role::Member->value)
                    ->where('workspace_id', $this->user()->current_workspace_id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.can_view_omnichat' => ['sometimes', 'boolean'],
            'permissions.*.can_reply_omnichat' => ['sometimes', 'boolean'],
            'permissions.*.can_assign_conversations' => ['sometimes', 'boolean'],
            'permissions.*.can_access_content' => ['sometimes', 'boolean'],
            'permissions.*.can_create_posts' => ['sometimes', 'boolean'],
            'permissions.*.can_edit_posts' => ['sometimes', 'boolean'],
            'permissions.*.can_approve_posts' => ['sometimes', 'boolean'],
            'permissions.*.can_publish_posts' => ['sometimes', 'boolean'],
            'permissions.*.can_delete_posts' => ['sometimes', 'boolean'],
        ];
    }
}
