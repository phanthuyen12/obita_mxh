<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatTag;
use App\Support\Omnichat\AccessibleChannelResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViewController extends Controller
{
    public function __construct(private readonly AccessibleChannelResolver $channelResolver) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $this->authorize('view', $workspace);

        $resolved = $this->channelResolver->resolve($user, $workspace);

        return Inertia::render('omnichat/LiveChat', [
            'workspaceId' => $workspace->id,
            'connectedChannels' => $resolved['connectedChannels'],
            'labels' => OmnichatTag::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'permissions' => [
                'manageChannels' => $user->can('manageAccounts', $workspace),
                'assignConversations' => $user->can('assignConversations', $workspace),
                'sendMessages' => $user->can('viewOmnichat', $workspace),
                'editContacts' => true,
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->photo_url,
            ],
        ]);
    }
}
