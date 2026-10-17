<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('manageEmailCampaign', $workspace);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
