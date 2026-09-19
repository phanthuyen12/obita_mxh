<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;

class CreateMemberAccount
{
    /**
     * Create a new user and attach them to the given workspace with the specified role.
     * This bypasses the invite email flow — admin creates credentials on behalf of the user.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public static function execute(Workspace $workspace, array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_id' => $workspace->account_id,
            'email_verified_at' => now(),
        ]);

        $role = WorkspaceRole::from($data['role']);

        $workspace->members()->attach($user->id, [
            'role' => $role->value,
        ]);

        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }
}
