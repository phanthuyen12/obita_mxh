<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Tag\CreateTag;
use App\Actions\Tag\DeleteTag;
use App\Actions\Tag\UpdateTag;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostTagController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);
        Tag::ensureDefaultTags($workspace);

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $tags = $workspace->tags()
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', $likeOperator, "%{$search}%"))
            ->latest()
            ->paginate(config('app.pagination.default'));

        return Inertia::render('topics/Index', [
            'workspace' => $workspace,
            'tags' => Inertia::scroll(fn () => $tags),
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);
        CreateTag::execute($workspace, $request->validate($this->rules($workspace->id)));

        return to_route('app.post-tags.index')->with([
            'flash.banner' => 'Thẻ bài viết đã được tạo thành công.',
            'flash.bannerStyle' => 'success',
        ]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_if(! $workspace || $tag->workspace_id !== $workspace->id, 404);
        $this->authorize('createPost', $workspace);

        UpdateTag::execute($tag, $request->validate($this->rules($workspace->id, $tag)));

        return to_route('app.post-tags.index')->with([
            'flash.banner' => 'Thẻ bài viết đã được cập nhật thành công.',
            'flash.bannerStyle' => 'success',
        ]);
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_if(! $workspace || $tag->workspace_id !== $workspace->id, 404);
        $this->authorize('createPost', $workspace);
        DeleteTag::execute($tag);

        return to_route('app.post-tags.index')->with([
            'flash.banner' => 'Đã xóa thẻ bài viết.',
            'flash.bannerStyle' => 'success',
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(string $workspaceId, ?Tag $tag = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')->where('workspace_id', $workspaceId)->ignore($tag)],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
