<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\WebsiteChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'events' => ['required', 'array', 'min:1', 'max:3'],
            'events.*' => ['required', 'string', Rule::in(['message.created', 'conversation.tagged'])],
            'rotate_secret' => ['sometimes', 'boolean'],
        ];
    }
}
