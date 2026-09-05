<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class StoreWordPressSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()?->can('manageAccounts', $workspace) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'application_password' => ['required', 'string'],
        ];
    }
}
