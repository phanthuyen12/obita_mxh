<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\ContentWorkflow\StoreContentWorkflowRequest;
use App\Http\Requests\App\ContentWorkflow\UpdateContentWorkflowRequest;
use App\Models\ContentWorkflow;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentWorkflowController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);
        $workspace->loadMissing('account.owner');
        $members = $workspace->members()->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($member): array => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email]);
        if ($owner = $workspace->account?->owner) {
            $members->prepend(['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email]);
        }

        return Inertia::render('workflow/Index', [
            'workflows' => $workspace->contentWorkflows()->with(['socialAccount', 'members'])->latest()->get(),
            'socialAccounts' => $workspace->socialAccounts()->where('is_active', true)->orderBy('display_name')->get(),
            'members' => $members->unique('id')->values(),
            'canManageTeam' => $request->user()->can('manageTeam', $workspace),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(StoreContentWorkflowRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $data = $request->validated();
        $socialAccountIds = array_values(array_filter((array) ($data['social_account_ids'] ?? [])));
        foreach ($socialAccountIds as $accountId) {
            $this->ensureAccountBelongsToWorkspace($workspace->id, $accountId);
        }
        $workflow = $workspace->contentWorkflows()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'social_account_id' => $socialAccountIds[0] ?? null,
            'social_account_ids' => $socialAccountIds,
            'created_by' => $request->user()->id,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $this->syncMembers($workflow, $data['members'] ?? [], $socialAccountIds);

        return back()->with('flash.banner', 'Đã tạo luồng nội dung.');
    }

    public function update(UpdateContentWorkflowRequest $request, ContentWorkflow $workflow): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workflow->workspace_id === $workspace->id, 404);
        $data = $request->validated();
        $socialAccountIds = array_values(array_filter((array) ($data['social_account_ids'] ?? [])));
        foreach ($socialAccountIds as $accountId) {
            $this->ensureAccountBelongsToWorkspace($workspace->id, $accountId);
        }
        $workflow->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'social_account_id' => $socialAccountIds[0] ?? null,
            'social_account_ids' => $socialAccountIds,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $this->syncMembers($workflow, $data['members'] ?? [], $socialAccountIds);

        return back()->with('flash.banner', 'Đã cập nhật luồng nội dung.');
    }

    public function destroy(Request $request, ContentWorkflow $workflow): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('manageTeam', $workspace);
        abort_unless($workflow->workspace_id === $workspace->id, 404);
        $workflow->delete();

        return back()->with('flash.banner', 'Đã xóa luồng nội dung.');
    }

    private function syncMembers(ContentWorkflow $workflow, array $members, array $socialAccountIds): void
    {
        $workspace = $workflow->workspace()->with(['account', 'members'])->firstOrFail();
        $allowedUserIds = $workspace->members->pluck('id');
        if ($ownerId = $workspace->account?->owner_id) {
            $allowedUserIds->push($ownerId);
        }

        $accountIds = $socialAccountIds !== []
            ? collect($socialAccountIds)
            : $workspace->socialAccounts()->pluck('id');

        $memberData = collect($members)
            ->filter(fn (array $member): bool => $allowedUserIds->contains($member['user_id'] ?? null));

        $activeMembers = $memberData->filter(fn (array $member): bool => ($member['can_write'] ?? false) || ($member['can_review'] ?? false) || ($member['can_publish'] ?? false)
        );

        foreach ($activeMembers as $member) {
            $userId = (string) ($member['user_id'] ?? '');
            if ($userId === '') {
                continue;
            }

            $canReview = (bool) ($member['can_review'] ?? false);

            foreach ($accountIds as $accountId) {
                $account = SocialAccount::query()
                    ->whereKey($accountId)
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if (! $account) {
                    continue;
                }

                $existingAccess = $account->sharedUsers()->whereKey($userId)->first();
                if ($existingAccess) {
                    $account->sharedUsers()->updateExistingPivot($userId, [
                        'can_access_content' => true,
                        'can_approve_posts' => $existingAccess->pivot->can_approve_posts || $canReview,
                    ]);
                } else {
                    $account->sharedUsers()->attach($userId, [
                        'granted_by_user_id' => auth()->id(),
                        'can_view_omnichat' => true,
                        'can_reply_omnichat' => true,
                        'can_assign_conversations' => false,
                        'can_access_content' => true,
                        'can_create_posts' => true,
                        'can_edit_posts' => true,
                        'can_approve_posts' => $canReview,
                        'can_publish_posts' => true,
                        'can_delete_posts' => false,
                    ]);
                }
            }
        }

        $workflow->members()->sync($activeMembers
            ->mapWithKeys(fn (array $member): array => [(string) $member['user_id'] => [
                'can_write' => (bool) ($member['can_write'] ?? false),
                'can_review' => (bool) ($member['can_review'] ?? false),
                'can_publish' => (bool) ($member['can_publish'] ?? false),
            ]])->all());
    }

    private function ensureAccountBelongsToWorkspace(string $workspaceId, string $accountId): void
    {
        abort_unless(SocialAccount::query()->whereKey($accountId)->where('workspace_id', $workspaceId)->exists(), 422, 'Trang không thuộc không gian làm việc này.');
    }
}
