<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteChatChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()?->can('manageAccounts', $workspace) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'authorized_origins' => ['required', 'array', 'min:1', 'max:20'],
            'authorized_origins.*' => ['required', 'url:http,https', 'max:255', 'distinct'],
            'welcome_message' => ['required', 'string', 'max:500'],
            'offline_message' => ['required', 'string', 'max:500'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['required', 'in:left,right'],
            'privacy_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
