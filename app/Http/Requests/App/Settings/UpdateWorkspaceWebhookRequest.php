<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceWebhookRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url', 'max:2000'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
