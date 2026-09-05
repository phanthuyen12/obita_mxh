<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\WebsiteChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'support_request' => ['required', 'string', 'max:5000'],
            'locale' => ['nullable', 'string', 'max:12'],
            'context' => ['nullable', 'array', 'max:50'],
            'context.*' => ['nullable'],
        ];
    }
}
