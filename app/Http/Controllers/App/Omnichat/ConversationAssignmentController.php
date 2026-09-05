<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\UpdateConversationAssignmentRequest;
use App\Models\OmnichatConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ConversationAssignmentController extends Controller
{
    public function update(UpdateConversationAssignmentRequest $request, OmnichatConversation $conversation): JsonResponse
    {
        $userId = $request->validated('user_id');

        if ($userId !== null) {
            if (! $conversation->socialAccount->userHasAccess(User::findOrFail($userId), 'can_view_omnichat')) {
                throw ValidationException::withMessages([
                    'user_id' => 'Người dùng chưa được cấp quyền Omnichat cho Page này.',
                ]);
            }
        }

        $conversation->update(['assigned_user_id' => $userId]);
        $conversation->load('assignedUser');

        $payload = [
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
                'avatar_url' => $conversation->assignedUser->photo_url,
            ] : null,
        ];

        return response()->json($payload);
    }
}
