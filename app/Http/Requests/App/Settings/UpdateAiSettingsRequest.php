<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Settings;

use App\Services\Ai\AiConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAccountOwner() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'content_clone_ai_provider' => ['required', Rule::in(AiConfiguration::contentCloneProviders())],
            'ai_text_provider' => ['required', Rule::in(AiConfiguration::textProviders())],
            'ai_image_provider' => ['required', Rule::in(AiConfiguration::imageProviders())],
            'dify_base_url' => ['required', 'url', 'max:255'],
            'dify_api_key' => ['nullable', 'string', 'max:5000'],
            'dify_text_api_key' => ['nullable', 'string', 'max:5000'],
            'dify_image_api_key' => ['nullable', 'string', 'max:5000'],
            'dify_content_clone_api_key' => ['nullable', 'string', 'max:5000'],
            'dify_connect_timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'dify_timeout' => ['required', 'integer', 'min:10', 'max:300'],
            'openai_api_key' => ['nullable', 'string', 'max:5000'],
            'anthropic_api_key' => ['nullable', 'string', 'max:5000'],
            'gemini_api_key' => ['nullable', 'string', 'max:5000'],
            'openrouter_api_key' => ['nullable', 'string', 'max:5000'],
            'openai_text_model' => ['nullable', 'string', 'max:255'],
            'openai_image_model' => ['nullable', 'string', 'max:255'],
            'anthropic_text_model' => ['nullable', 'string', 'max:255'],
            'gemini_text_model' => ['nullable', 'string', 'max:255'],
            'gemini_image_model' => ['nullable', 'string', 'max:255'],
            'openrouter_text_model' => ['nullable', 'string', 'max:255'],
            'openrouter_image_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
