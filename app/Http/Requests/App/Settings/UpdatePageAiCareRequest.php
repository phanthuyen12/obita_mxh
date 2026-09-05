<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePageAiCareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $this->user() !== null && ($this->user()->isAccountOwner() || ($workspace && $this->user()->can('manageTeam', $workspace)));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
            'bot_name' => ['nullable', 'string', 'max:100'],
            'persona' => ['nullable', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:100'],
            'dify_api_key' => ['nullable', 'string', 'max:500'],
            'dify_base_url' => ['nullable', 'string', 'max:255'],
            'dify_app_type' => ['nullable', 'string', 'max:50'],
            'suggested_questions' => ['nullable', 'array'],
            'suggested_questions.*' => ['string', 'max:255'],
            'reply_mode' => ['nullable', 'string'],
            'reply_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'operating_hours' => ['nullable', 'array'],
            'off_hours_behavior' => ['nullable', 'string'],
            'off_hours_message' => ['nullable', 'string', 'max:1000'],
            'auto_tag_leads' => ['nullable', 'boolean'],
            'lead_keywords' => ['nullable', 'array'],
            'lead_keywords.*' => ['string', 'max:100'],
            'knowledge_base' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
