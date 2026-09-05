<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\StoreTagRequest;
use App\Models\OmnichatTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = OmnichatTag::query()->create([
            'workspace_id' => $request->user()->current_workspace_id,
            ...$request->validated(),
        ]);

        return response()->json(['tag' => $tag->only(['id', 'name', 'color'])], 201);
    }

    public function destroy(Request $request, OmnichatTag $tag): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);
        abort_unless($tag->workspace_id === $workspace->id, 404);

        $tag->delete();

        return response()->json(['success' => true]);
    }
}
