<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\SocialAccountGroupRequest;
use App\Models\SocialAccountGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialAccountGroupController extends Controller
{
    public function show(Request $request, SocialAccountGroup $group): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace && $request->user()->can('manageAccounts', $workspace), 403);
        $this->ensureCurrentWorkspace($request, $group);

        return response()->json([
            'id' => $group->id,
            'name' => $group->name,
            'social_account_ids' => $group->socialAccounts()->pluck('social_accounts.id'),
        ]);
    }

    public function store(SocialAccountGroupRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $group = $workspace->socialAccountGroups()->create([
            'name' => $request->validated('name'),
        ]);

        $group->socialAccounts()->syncOrFail(
            $request->validated('social_account_ids'),
        );

        return back()->with('flash.banner', 'Đã tạo nhóm Page.');
    }

    public function update(
        SocialAccountGroupRequest $request,
        SocialAccountGroup $group,
    ): RedirectResponse {
        $this->ensureCurrentWorkspace($request, $group);

        $group->update(['name' => $request->validated('name')]);
        $group->socialAccounts()->syncOrFail(
            $request->validated('social_account_ids'),
        );

        return back()->with('flash.banner', 'Đã cập nhật nhóm Page.');
    }

    public function destroy(
        Request $request,
        SocialAccountGroup $group,
    ): RedirectResponse {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && $request->user()->can('manageAccounts', $workspace),
            403,
        );
        $this->ensureCurrentWorkspace($request, $group);

        $group->delete();

        return back()->with('flash.banner', 'Đã xóa nhóm Page.');
    }

    private function ensureCurrentWorkspace(
        Request $request,
        SocialAccountGroup $group,
    ): void {
        abort_unless(
            $request->user()->current_workspace_id === $group->workspace_id,
            404,
        );
    }
}
