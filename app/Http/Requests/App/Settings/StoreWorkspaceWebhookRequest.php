<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceWebhookRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
