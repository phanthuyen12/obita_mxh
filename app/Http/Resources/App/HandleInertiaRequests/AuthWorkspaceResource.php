<?php

declare(strict_types=1);

namespace App\Http\Resources\App\HandleInertiaRequests;

use App\Models\User;
use App\Models\Workspace;

class AuthWorkspaceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Workspace $workspace, ?User $user = null): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'logo_url' => $workspace->logo_url,
            'created_at' => $workspace->created_at->toIso8601String(),
            'role' => $user ? self::resolveRole($workspace, $user) : null,
            'can_content' => $user ? self::resolveFeature($workspace, $user, 'can_content') : null,
            'can_omnichat' => $user ? self::resolveFeature($workspace, $user, 'can_omnichat') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'logo_url' => $workspace->logo_url,
        ];
    }

    private static function resolveRole(Workspace $workspace, User $user): ?string
    {
        if ($user->isAccountOwner() && $workspace->account_id === $user->account_id) {
            return 'owner';
        }

        return $workspace->members()
            ->where('users.id', $user->id)
            ->first()
            ?->pivot
            ?->role;
    }

    /**
     * Resolves a boolean feature permission for the given user in the workspace.
     *
     * Owners and workspace admins always get true. Regular members read the
     * value from the pivot column. Returns null when the user has no role.
     */
    private static function resolveFeature(Workspace $workspace, User $user, string $feature): bool
    {
        // Account owner or workspace admin always has access.
        if ($user->isAccountOwner() && $workspace->account_id === $user->account_id) {
            return true;
        }

        $member = $workspace->members()
            ->where('users.id', $user->id)
            ->first();

        if (! $member) {
            return false;
        }

        // Admins inherit all feature permissions.
        if ($member->pivot->role === 'admin') {
            return true;
        }

        return (bool) $member->pivot->{$feature};
    }
}
