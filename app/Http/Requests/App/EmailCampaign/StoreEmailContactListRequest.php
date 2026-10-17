<?php

declare(strict_types=1);

namespace App\Http\Requests\App\EmailCampaign;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailContactListRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'max:'.(int) config('trypost.email_campaigns.import_max_size_kb', 20480),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Chỉ hỗ trợ file CSV hoặc Excel (.xlsx).'),
        ];
    }
}
