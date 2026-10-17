<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use App\Enums\EmailCampaign\RecipientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowEmailCampaignRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(RecipientStatus::class)],
            'search' => ['nullable', 'string', 'max:190'],
        ];
    }
}
