<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'preview_text' => ['nullable', 'string', 'max:200'],
            'body_html' => ['required', 'string'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'email_contact_list_id' => [
                'required',
                'uuid',
                Rule::exists('email_contact_lists', 'id')
                    ->where('workspace_id', $this->user()->current_workspace_id),
            ],
            'delay_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
