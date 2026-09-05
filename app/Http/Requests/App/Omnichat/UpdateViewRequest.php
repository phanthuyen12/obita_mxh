<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = $user?->currentWorkspace;

        return $workspace !== null && $user->can('view', $workspace);
    }

    public function rules(): array
    {
        $workspaceId = $this->user()?->current_workspace_id;

        return [
            'channel_ids' => ['required', 'array', 'min:1'],
            'channel_ids.*' => [
                'required',
                'uuid',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail) use ($workspaceId): void {
                    $isSocialAccount = SocialAccount::query()->whereKey($value)->where('workspace_id', $workspaceId)->exists();
                    $isWebsiteChannel = OmnichatChannel::query()
                        ->whereKey($value)
                        ->where('workspace_id', $workspaceId)
                        ->where('provider', 'website')
                        ->exists();

                    if (! $isSocialAccount && ! $isWebsiteChannel) {
                        $fail('The selected channel is invalid.');
                    }
                },
            ],
        ];
    }
}
