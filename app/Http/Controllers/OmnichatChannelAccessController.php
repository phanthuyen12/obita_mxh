<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOmnichatChannelAccessRequest;
use App\Models\OmnichatChannel;
use Illuminate\Http\RedirectResponse;

class OmnichatChannelAccessController extends Controller
{
    public function update(UpdateOmnichatChannelAccessRequest $request, OmnichatChannel $channel): RedirectResponse
    {
        $defaults = [
            'can_view_omnichat' => true,
            'can_reply_omnichat' => true,
            'can_assign_conversations' => false,
        ];
        $permissions = collect($request->input('permissions', []));
        $userIds = collect($request->input('user_ids', []))->mapWithKeys(fn (string $userId): array => [
            $userId => [
                'granted_by_user_id' => $request->user()->id,
                ...$defaults,
                ...(array) $permissions->get($userId, []),
            ],
        ]);

        $channel->sharedUsers()->sync($userIds);

        return back()->with('flash.banner', 'Đã cập nhật quyền truy cập Website Live Chat.')
            ->with('flash.bannerStyle', 'success');
    }
}
