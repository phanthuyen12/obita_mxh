<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\UpdateConversationTagsRequest;
use App\Models\OmnichatConversation;
use Illuminate\Http\JsonResponse;

class ConversationTagController extends Controller
{
    public function update(
        UpdateConversationTagsRequest $request,
        OmnichatConversation $conversation,
    ): JsonResponse {
        abort_unless($conversation->workspace_id === $request->user()->current_workspace_id, 404);

        $conversation->tags()->sync($request->validated('tag_ids'));
        $tags = $conversation->tags()->orderBy('name')->get(['omnichat_tags.id', 'name', 'color']);

        return response()->json(['tags' => $tags]);
    }
}
