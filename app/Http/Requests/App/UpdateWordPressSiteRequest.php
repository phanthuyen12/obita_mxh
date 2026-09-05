<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

class UpdateWordPressSiteRequest extends StoreWordPressSiteRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'application_password' => ['nullable', 'string'],
        ];
    }
}
