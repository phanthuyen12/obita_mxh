<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Enums\UserWorkspace\Role;
use Database\Factories\OmnichatChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmnichatChannel extends Model
{
    /** @use HasFactory<OmnichatChannelFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id', 'social_account_id', 'provider', 'external_id', 'name',
        'avatar_url', 'access_token', 'refresh_token', 'token_expires_at',
        'refresh_token_expires_at', 'webhook_secret', 'capabilities', 'settings',
        'status', 'last_synced_at', 'connected_at', 'disconnected_at', 'public_key_hash',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'webhook_secret', 'settings'];

    protected $attributes = ['status' => ChannelStatus::Connected->value];

    protected function casts(): array
    {
        return [
            'provider' => ChannelProvider::class,
            'status' => ChannelStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'capabilities' => 'array',
            'settings' => 'encrypted:array',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(OmnichatConversation::class, 'channel_id');
    }

    public function webchatSessions(): HasMany
    {
        return $this->hasMany(OmnichatWebchatSession::class, 'channel_id');
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'omnichat_channel_accesses')
            ->withPivot(['granted_by_user_id', 'can_view_omnichat', 'can_reply_omnichat', 'can_assign_conversations'])
            ->withTimestamps();
    }

    public function userHasAccess(User $user, string $permission): bool
    {
        if ($this->workspace_id !== $user->current_workspace_id) {
            return false;
        }

        if ($user->isAccountOwner()) {
            return true;
        }

        $isWorkspaceAdmin = $user->workspaces()
            ->whereKey($this->workspace_id)
            ->wherePivot('role', Role::Admin->value)
            ->exists();

        if ($isWorkspaceAdmin) {
            return true;
        }

        $access = $this->sharedUsers()->whereKey($user->id)->first()?->pivot;

        return $access !== null && (bool) ($access->{$permission} ?? false);
    }

    #[Scope]
    protected function connected(Builder $query): Builder
    {
        return $query->where('status', ChannelStatus::Connected);
    }

    #[Scope]
    protected function forProvider(Builder $query, ChannelProvider $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function markAsDisconnected(?string $reason = null): void
    {
        $settings = $this->settings ?? [];

        if ($reason !== null) {
            $settings['disconnect_reason'] = $reason;
        }

        $this->update([
            'status' => ChannelStatus::Disconnected,
            'settings' => $settings,
            'disconnected_at' => now(),
        ]);
    }
}
