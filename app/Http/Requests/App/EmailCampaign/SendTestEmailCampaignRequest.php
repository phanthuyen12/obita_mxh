<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailCampaignRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'preview_text' => ['nullable', 'string', 'max:200'],
            'body_html' => ['required', 'string', 'max:500000'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
        ];
    }
}
