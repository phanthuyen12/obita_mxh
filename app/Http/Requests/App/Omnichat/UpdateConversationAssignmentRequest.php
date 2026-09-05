<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use App\Models\OmnichatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof OmnichatConversation
            && $conversation->workspace_id === $this->user()?->current_workspace_id
            && $this->user()->can('assign', $conversation);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('account_id', $this->user()->account_id);
                }),
            ],
        ];
    }
}
