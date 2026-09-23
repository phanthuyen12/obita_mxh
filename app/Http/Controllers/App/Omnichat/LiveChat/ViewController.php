<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatTag;
use App\Models\User;
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

        $assignees = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspace->id))
            ->orderBy('name')
            ->get(['id', 'name', 'profile_photo_path'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->photo_url,
            ])
            ->all();

        return Inertia::render('omnichat/LiveChat', [
            'workspaceId' => $workspace->id,
            'connectedChannels' => $resolved['connectedChannels'],
            'assignees' => $assignees,
            'labels' => OmnichatTag::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'permissions' => [
                'manageChannels' => $user->can('manageAccounts', $workspace),
                'assignConversations' => $user->can('assignConversations', $workspace),
                'sendMessages' => $user->can('viewOmnichat', $workspace),
                'editContacts' => true,
                'manageAiSettings' => $user->isAccountOwner() || $user->can('manageTeam', $workspace),
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->photo_url,
            ],
        ]);
    }
}
