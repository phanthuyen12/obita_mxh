<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebsiteChat;

use App\Enums\Omnichat\ChannelProvider;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\WebsiteChat\ListConversationsRequest;
use App\Http\Resources\Api\WebsiteChatConversationResource;
use App\Models\OmnichatChannel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    public function index(ListConversationsRequest $request, OmnichatChannel $channel): AnonymousResourceCollection
    {
        abort_unless(
            $channel->workspace_id === $request->user()->current_workspace_id
            && $channel->provider === ChannelProvider::Website,
            Response::HTTP_NOT_FOUND,
        );

        $search = $request->string('search')->trim()->toString();
        $conversations = $channel->conversations()
            ->select([
                'id', 'channel_id', 'contact_id', 'external_id', 'status', 'priority',
                'last_message_preview', 'last_message_at', 'last_inbound_at', 'last_outbound_at',
                'meta', 'created_at', 'updated_at',
            ])
            ->with([
                'contact:id,display_name,email,phone,avatar_url,locale',
                'tags:id,name,color',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
            ->when($request->filled('updated_after'), fn ($query) => $query->where('updated_at', '>', $request->date('updated_after')))
            ->when($search !== '', fn ($query) => $query->whereHas('contact', fn ($contactQuery) => $contactQuery
                ->where('display_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')))
            ->latest('last_message_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return WebsiteChatConversationResource::collection($conversations);
    }
}
