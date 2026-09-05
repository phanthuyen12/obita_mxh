<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessShopeeWebhook;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopeeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();
        $data = is_array(data_get($payload, 'data')) ? data_get($payload, 'data') : [];
        $content = data_get($data, 'content');
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = is_array($decoded) ? $decoded : [];
        }
        $shopId = (string) (data_get($payload, 'shop_id') ?: data_get($data, 'shop_id') ?: data_get($content, 'shop_id') ?: data_get($content, 'to_shop_id'));
        $messageId = (string) (data_get($content, 'message_id') ?: data_get($content, 'msg_id') ?: data_get($data, 'message_id') ?: data_get($payload, 'msg_id'));
        if ($shopId === '' || $messageId === '') {
            return response('OK');
        }
        $account = SocialAccount::query()->where('platform', Platform::Shopee)->where('platform_user_id', $shopId)->first();
        if ($account === null) {
            return response('OK');
        }
        $event = OmnichatWebhookEvent::query()->firstOrCreate(
            ['provider' => 'shopee', 'external_event_id' => $shopId.':'.$messageId],
            ['workspace_id' => $account->workspace_id, 'social_account_id' => $account->id,
                'event_type' => (string) (data_get($content, 'type') ?: data_get($data, 'type') ?: 'message'),
                'payload' => $payload, 'status' => 'pending', 'received_at' => now()],
        );
        if ($event->wasRecentlyCreated) {
            ProcessShopeeWebhook::dispatch($event);
        }

        return response('OK');
    }
}
