<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSocialAccountAccessRequest;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;

class SocialAccountAccessController extends Controller
{
    public function update(UpdateSocialAccountAccessRequest $request, SocialAccount $account): RedirectResponse
    {
        $defaults = [
            'can_view_omnichat' => true,
            'can_reply_omnichat' => true,
            'can_assign_conversations' => false,
            'can_access_content' => true,
            'can_create_posts' => true,
            'can_edit_posts' => true,
            'can_approve_posts' => false,
            'can_publish_posts' => true,
            'can_delete_posts' => false,
        ];
        $permissions = collect($request->input('permissions', []));
        $userIds = collect($request->input('user_ids', []))->mapWithKeys(fn (string $userId): array => [
            $userId => [
                'granted_by_user_id' => $request->user()->id,
                ...$defaults,
                ...(array) $permissions->get($userId, []),
            ],
        ]);

        $account->sharedUsers()->sync($userIds);

        return back()->with('flash.banner', __('accounts.flash.access_updated'))
            ->with('flash.bannerStyle', 'success');
    }
}
