<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use App\Enums\EmailCampaign\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmailCampaignRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ];
    }
}
