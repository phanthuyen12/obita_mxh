<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramChannelRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'ai_care' => ['sometimes', 'array'],
            'ai_care.enabled' => ['sometimes', 'boolean'],
            'ai_care.provider' => ['sometimes', 'string'],
            'ai_care.dify_api_key' => ['nullable', 'string'],
            'ai_care.dify_base_url' => ['nullable', 'string', 'url:http,https'],
            'ai_care.persona' => ['nullable', 'string'],
            'ai_care.knowledge_base' => ['nullable', 'string'],
            'ai_care.reply_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:30'],
            'ai_care.auto_tag_leads' => ['nullable', 'boolean'],
            'ai_care.lead_keywords' => ['nullable', 'array'],
            'ai_care.operating_hours' => ['nullable', 'array'],
            'ai_care.off_hours_behavior' => ['nullable', 'string'],
            'ai_care.off_hours_message' => ['nullable', 'string', 'max:500'],
            'ai_care.handover_message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
