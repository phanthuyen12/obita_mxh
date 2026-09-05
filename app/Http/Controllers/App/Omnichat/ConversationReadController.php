<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationReadController extends Controller
{
    public function __invoke(Request $request, OmnichatConversation $conversation): JsonResponse
    {
        abort_unless($request->user()->currentWorkspace?->id === $conversation->workspace_id, 404);

        $isUnread = $request->boolean('unread', false);

        if ($isUnread) {
            $conversation->update(['meta' => array_replace($conversation->meta ?? [], ['unread_count' => 1])]);
            $conversation->messages()->where('direction', 'inbound')->latest('created_at')->limit(1)->update(['read_at' => null, 'status' => 'delivered']);
        } else {
            $conversation->update(['meta' => array_replace($conversation->meta ?? [], ['unread_count' => 0])]);
            $conversation->messages()->where('direction', 'inbound')->whereNull('read_at')->update(['read_at' => now(), 'status' => 'read']);
        }

        return response()->json([
            'success' => true,
            'unread_count' => $isUnread ? 1 : 0,
        ]);
    }
}
