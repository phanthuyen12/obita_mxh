<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class SocialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'network' => $this->platform->network(),
            'platform_user_id' => $this->platform_user_id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'display_label' => $this->display_label,
            'handle_label' => $this->handle_label,
            'avatar_url' => $this->avatar_url,
            'profile_url' => $this->profile_url,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_syncing' => Cache::has("account:syncing:{$this->id}"),
            'error_message' => $this->error_message,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'connected_by_user_id' => $this->connected_by_user_id,
            'ownership_type' => $this->connected_by_user_id === $request->user()?->id ? 'owned' : 'shared',
            'can_disconnect' => $request->user()?->can('manage', $this->resource) ?? false,
            'can_share' => $request->user()?->can('share', $this->resource) ?? false,
            'shared_user_ids' => $this->whenLoaded(
                'sharedUsers',
                fn (): array => $this->sharedUsers->pluck('id')->values()->all(),
                [],
            ),
            'shared_user_permissions' => $this->whenLoaded(
                'sharedUsers',
                fn (): array => $this->sharedUsers->mapWithKeys(fn ($user): array => [
                    $user->id => collect([
                        'can_view_omnichat',
                        'can_reply_omnichat',
                        'can_assign_conversations',
                        'can_access_content',
                    ])->mapWithKeys(fn (string $permission): array => [$permission => (bool) $user->pivot->{$permission}])->all(),
                ])->all(),
                [],
            ),
        ];
    }
}
