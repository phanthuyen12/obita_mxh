<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\UpdateViewRequest;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ViewController extends Controller
{
    public function __invoke(UpdateViewRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var list<string> $selectedChannelIds */
        $selectedChannelIds = array_values($request->validated('channel_ids'));
        $socialAccountIds = SocialAccount::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->omnichatAccessibleBy($user)
            ->whereIn('id', $selectedChannelIds)
            ->pluck('id')->all();
        $validSelectedIds = array_values(array_filter($selectedChannelIds, fn (string $id): bool => in_array($id, $socialAccountIds, true)));
        $focusedChannelId = in_array($user->current_omnichat_social_account_id, $socialAccountIds, true)
            ? $user->current_omnichat_social_account_id
            : ($validSelectedIds[0] ?? null);

        DB::transaction(function () use ($user, $socialAccountIds, $focusedChannelId): void {
            $user->omnichatViewSocialAccounts()->sync($socialAccountIds);
            $user->update(['current_omnichat_social_account_id' => $focusedChannelId]);
        });

        return response()->json([
            'selected_channel_ids' => $selectedChannelIds,
            'focused_channel_id' => $focusedChannelId ?? $selectedChannelIds[0],
        ]);
    }
}
