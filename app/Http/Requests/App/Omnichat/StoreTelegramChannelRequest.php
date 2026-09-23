<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Illuminate\Foundation\Http\FormRequest;

class StoreTelegramChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()?->can('manageAccounts', $workspace) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'mode' => ['sometimes', 'string', 'in:bot,business'],
        ];
    }
}
