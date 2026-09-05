<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Actions\Omnichat\SyncShopeeConversations;
use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopeeSyncController extends Controller
{
    public function __invoke(Request $request, SocialAccount $account, SyncShopeeConversations $sync): JsonResponse
    {
        abort_unless($request->user()->currentWorkspace?->id === $account->workspace_id && $account->platform === Platform::Shopee, 404);

        return response()->json(['success' => true, 'conversations' => $sync->execute($account)]);
    }
}
