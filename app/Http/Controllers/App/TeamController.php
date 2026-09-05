<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Team\StoreTeamRequest;
use App\Http\Requests\App\Team\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageTeam', $workspace);

        return Inertia::render('settings/workspace/Teams', [
            'workspace' => $workspace->only(['id', 'name']),
            'teams' => $workspace->teams()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageTeam', $workspace);

        $search = trim((string) $request->input('search'));
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $members = $workspace->members()
            ->when($search !== '', fn ($query) => $query->where(function ($memberQuery) use ($search, $likeOperator): void {
                $memberQuery->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('email', $likeOperator, "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(30, ['users.id', 'name', 'email']);

        return response()->json($members);
    }

    public function memberIds(Request $request, Team $team): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageTeam', $workspace);
        abort_unless($team->workspace_id === $workspace->id, 404);

        return response()->json(['data' => $team->users()->pluck('users.id')]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        DB::transaction(function () use ($request, $workspace): void {
            $team = $workspace->teams()->create([
                ...$request->safe()->only(['name', 'description', 'is_active']),
                'created_by' => $request->user()->id,
            ]);

            $team->users()->sync($request->validated('user_ids', []));
        });

        return back()->with('flash.banner', 'Đã tạo Team.');
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team): void {
            $team->update($request->safe()->only(['name', 'description', 'is_active']));

            if ($request->has('user_ids')) {
                $team->users()->sync($request->validated('user_ids'));
            }
        });

        return back()->with('flash.banner', 'Đã cập nhật Team.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageTeam', $workspace);
        abort_unless($team->workspace_id === $workspace->id, 404);

        $team->delete();

        return back()->with('flash.banner', 'Đã xóa Team và các quyền Folder liên quan.');
    }
}
