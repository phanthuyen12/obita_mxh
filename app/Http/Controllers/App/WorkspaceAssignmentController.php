<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $this->authorize('manageTeam', $workspace);

        $memberId = $request->string('member')->trim()->toString();
        $platform = $request->string('platform')->trim()->toString();
        $module = $request->string('module')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $accounts = $workspace->socialAccounts()
            ->with(['connectedBy:id,name,email', 'sharedUsers:id,name,email'])
            ->when($platform !== '', fn ($query) => $query->where('platform', $platform))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $likeOperator): void {
                $query->where('display_name', $likeOperator, "%{$search}%")
                    ->orWhere('username', $likeOperator, "%{$search}%")
                    ->orWhere('platform_user_id', $likeOperator, "%{$search}%");
            }))
            ->whereHas('sharedUsers', function ($query) use ($memberId, $module): void {
                $query->when($memberId !== '', fn ($query) => $query->whereKey($memberId));

                if ($module === 'omnichat') {
                    $query->where('social_account_accesses.can_view_omnichat', true);
                }

                if ($module === 'content') {
                    $query->where('social_account_accesses.can_access_content', true);
                }
            })
            ->orderBy('display_name')
            ->get();

        $rows = $accounts->flatMap(fn (SocialAccount $account) => $account->sharedUsers
            ->filter(function ($member) use ($memberId, $module): bool {
                if ($memberId !== '' && $member->id !== $memberId) {
                    return false;
                }

                return match ($module) {
                    'omnichat' => (bool) $member->pivot->can_view_omnichat,
                    'content' => (bool) $member->pivot->can_access_content,
                    default => true,
                };
            })
            ->map(
                fn ($member): array => [
                    'id' => "{$account->id}:{$member->id}",
                    'member' => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                    ],
                    'account' => [
                        'id' => $account->id,
                        'platform' => $account->platform->value,
                        'platform_label' => $account->platform->label(),
                        'display_name' => $account->display_label,
                        'username' => $account->handle_label,
                        'avatar_url' => $account->avatar_url,
                        'status' => $account->status->value,
                        'status_label' => $account->status->label(),
                        'connected_by' => $account->connectedBy?->name,
                    ],
                    'permissions' => [
                        'omnichat' => collect([
                            'can_view_omnichat', 'can_reply_omnichat', 'can_assign_conversations',
                        ])->filter(fn (string $permission): bool => (bool) $member->pivot->{$permission})->values()->all(),
                        'content' => (bool) $member->pivot->can_access_content
                            ? ['can_access_content']
                            : [],
                    ],
                ],
            ))->values()->all();

        $members = $workspace->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']);
        $platforms = $workspace->socialAccounts()->select('platform')->distinct()->orderBy('platform')->pluck('platform')
            ->map(fn (Platform $platform): array => [
                'value' => $platform->value,
                'label' => $platform->label(),
            ])
            ->filter()
            ->values();

        return Inertia::render('settings/workspace/Assignments', [
            'workspace' => ['id' => $workspace->id, 'name' => $workspace->name],
            'rows' => $rows,
            'members' => $members,
            'platforms' => $platforms,
            'filters' => compact('memberId', 'platform', 'module', 'search'),
            'summary' => [
                'assignments' => count($rows),
                'profiles' => $accounts->count(),
                'members' => collect($rows)->pluck('member.id')->unique()->count(),
            ],
        ]);
    }
}
