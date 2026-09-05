<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\WebsiteChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'type' => ['sometimes', 'string', Rule::in(['text', 'image', 'video', 'audio', 'document'])],
            'body' => ['nullable', 'required_without_all:attachments,image', 'string', 'max:5000'],
            'image' => ['nullable', 'required_without_all:body,attachments', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'attachments' => ['nullable', 'required_without_all:body,image', 'array', 'max:10'],
            'attachments.*' => ['required', 'array'],
            'attachments.*.id' => ['nullable', 'string', 'max:255'],
            'attachments.*.type' => ['required', 'string', Rule::in(['image', 'video', 'audio', 'document'])],
            'attachments.*.url' => ['required', 'url:http,https', 'max:2048'],
            'attachments.*.thumbnail_url' => ['nullable', 'url:http,https', 'max:2048'],
            'attachments.*.file_name' => ['nullable', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:120'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0', 'max:104857600'],
            'attachments.*.width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'attachments.*.height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'reply_to_message_id' => ['nullable', 'uuid'],
            'metadata' => ['nullable', 'array', 'max:50'],
            'metadata.*' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('type')) {
            $this->merge(['type' => $this->hasFile('image') ? 'image' : data_get($this->input('attachments'), '0.type', 'text')]);
        }
    }
}
