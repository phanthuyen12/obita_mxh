<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Omnichat;

use App\Events\OmnichatConversationTagged;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Omnichat\SyncConversationTagsRequest;
use App\Models\OmnichatConversation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ConversationTagController extends Controller
{
    public function update(SyncConversationTagsRequest $request, OmnichatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->workspace_id === $request->user()->current_workspace_id, Response::HTTP_NOT_FOUND);

        $conversation->tags()->sync($request->validated('tag_ids'));
        $tags = $conversation->tags()->orderBy('name')->get(['omnichat_tags.id', 'name', 'color']);
        OmnichatConversationTagged::dispatch($conversation, $tags->pluck('id')->all());

        return response()->json(['tags' => $tags]);
    }
}
