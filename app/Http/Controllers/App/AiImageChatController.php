<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAiImageChatJob;
use App\Models\ContentClonePreviewTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiImageChatController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'size' => ['nullable', 'string', 'in:1024x1024,1024x1792,1792x1024'],
            'quality' => ['nullable', 'string', 'in:low,medium,high'],
            'theme' => ['nullable', 'string', 'max:255'],
        ]);

        $request->session()->save();

        $task = ContentClonePreviewTask::create([
            'workspace_id' => $workspace->id,
            'status' => 'pending',
            'payload' => $validated,
        ]);

        ProcessAiImageChatJob::dispatch($task);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
        ]);
    }
}
