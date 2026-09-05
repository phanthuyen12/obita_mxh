<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Omnichat;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Omnichat\StoreTagRequest;
use App\Models\OmnichatTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tags = OmnichatTag::query()->where('workspace_id', $request->user()->current_workspace_id)
            ->orderBy('name')->get(['id', 'name', 'color']);

        return response()->json(['tags' => $tags]);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = OmnichatTag::query()->create([
            'workspace_id' => $request->user()->current_workspace_id,
            ...$request->validated(),
        ]);

        return response()->json(['tag' => $tag->only(['id', 'name', 'color'])], Response::HTTP_CREATED);
    }
}
