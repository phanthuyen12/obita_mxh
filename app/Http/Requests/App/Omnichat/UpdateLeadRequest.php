<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;
        $contact = $this->route('contact');

        return $workspace !== null
            && $this->user()->can('view', $workspace)
            && $contact?->workspace_id === $workspace->id;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'lead_stage' => ['sometimes', 'required', Rule::in(['new', 'qualified', 'contacted', 'converted', 'lost'])],
        ];
    }
}
