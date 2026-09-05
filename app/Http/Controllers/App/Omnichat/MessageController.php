<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Actions\Omnichat\StoreMessage;
use App\Events\OmnichatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\StoreMessageRequest;
use App\Models\OmnichatConversation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, OmnichatConversation $conversation, StoreMessage $storeMessage): JsonResponse
    {
        $message = $storeMessage->execute(
            $conversation,
            $request->user(),
            $request->string('body')->toString(),
            $request->string('mode')->toString(),
            $request->string('client_id')->toString(),
            $request->file('attachment') ?? $request->file('image'),
        );

        return response()->json(
            (new OmnichatMessageCreated($message))->broadcastWith(),
            Response::HTTP_CREATED,
        );
    }
}
