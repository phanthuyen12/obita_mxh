<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat\LiveChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConversationRequest extends FormRequest
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
            'tab' => ['nullable', 'string', Rule::in(['all', 'unread', 'mentions'])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string'],
        ];
    }
}
