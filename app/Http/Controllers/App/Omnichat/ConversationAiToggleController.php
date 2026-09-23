<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationAiToggleController extends Controller
{
    public function toggle(Request $request, OmnichatConversation $conversation): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($conversation->workspace_id === $workspace->id, 404);

        $meta = $conversation->meta ?? [];
        $isPaused = (bool) ($meta['ai_paused'] ?? false);
        $meta['ai_paused'] = ! $isPaused;

        $conversation->update(['meta' => $meta]);

        $statusMessage = $meta['ai_paused']
            ? 'Đã tạm dừng AI Bot cho hội thoại này để nhân viên tiếp quản.'
            : 'Đã kích hoạt lại AI Bot tự động trả lời cho hội thoại này.';

        if ($request->expectsJson()) {
            return response()->json([
                'ai_paused' => (bool) $meta['ai_paused'],
                'message' => $statusMessage,
            ]);
        }

        return back()->with('success', $statusMessage);
    }
}
