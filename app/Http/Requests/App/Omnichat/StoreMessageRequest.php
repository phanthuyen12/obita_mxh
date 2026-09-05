<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use App\Models\OmnichatConversation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof OmnichatConversation
            && $this->user()?->can('reply', $conversation) === true;
    }

    public function rules(): array
    {
        $conversation = $this->route('conversation');
        $attachmentMaxKilobytes = $conversation instanceof OmnichatConversation
            && $conversation->socialAccount?->platform?->value === 'facebook'
            ? 25 * 1024
            : 1024;

        return [
            'body' => ['nullable', 'required_without_all:attachment,image', 'string', 'max:10000'],
            'attachment' => [
                'nullable',
                'required_without_all:body,image',
                'file',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && $value->getSize() < 1) {
                        $fail('The attachment must not be empty.');
                    }
                },
                "max:{$attachmentMaxKilobytes}",
            ],
            'image' => [
                'nullable',
                'required_without_all:body,attachment',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif',
                'max:1024',
            ],
            'client_id' => ['required', 'uuid'],
            'mode' => ['required', Rule::in(['reply', 'internal'])],
        ];
    }
}
