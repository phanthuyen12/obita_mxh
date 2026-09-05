<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebsiteChat;

use App\Http\Controllers\Controller;
use App\Support\Omnichat\WebsiteChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __invoke(Request $request, WebsiteChatService $websiteChat): JsonResponse
    {
        $channel = $websiteChat->channelFromRequest($request);
        abort_if($channel === null || ! $websiteChat->originIsAllowed($channel, $websiteChat->requestOrigin($request)), 403);

        $settings = $channel->settings ?? [];

        return response()->json([
            'channel' => ['id' => $channel->id, 'name' => $channel->name],
            'branding' => [
                'primary_color' => data_get($settings, 'primary_color', '#2563EB'),
                'position' => data_get($settings, 'position', 'right'),
                'welcome_message' => data_get($settings, 'welcome_message', 'Xin chào! Chúng tôi có thể giúp gì cho bạn?'),
                'offline_message' => data_get($settings, 'offline_message', 'Vui lòng để lại lời nhắn.'),
                'privacy_url' => data_get($settings, 'privacy_url'),
            ],
            'capabilities' => $channel->capabilities ?? ['messages'],
        ]);
    }
}
