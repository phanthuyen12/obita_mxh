<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessTelegramOmnichatWebhook;
use App\Models\OmnichatChannel;
use App\Models\OmnichatWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TelegramOmnichatWebhookController extends Controller
{
    public function __invoke(Request $request, OmnichatChannel $channel): Response
    {
        // Ensure channel is Telegram and connected
        if ($channel->provider !== ChannelProvider::Telegram || $channel->status !== ChannelStatus::Connected) {
            return response()->noContent(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $expectedSecret = (string) $channel->webhook_secret;
        $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $receivedSecret)) {
            Log::warning('[TelegramOmnichat] Webhook rejected due to invalid secret token', [
                'channel_id' => $channel->id,
            ]);

            return response()->noContent(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();
        if ($payload === []) {
            return response()->noContent();
        }

        $updateId = data_get($payload, 'update_id');
        $eventType = match (true) {
            isset($payload['message']) => 'message',
            isset($payload['edited_message']) => 'edited_message',
            isset($payload['callback_query']) => 'callback_query',
            isset($payload['business_connection']) => 'business_connection',
            isset($payload['business_message']) => 'business_message',
            isset($payload['edited_business_message']) => 'edited_business_message',
            isset($payload['deleted_business_messages']) => 'deleted_business_messages',
            default => 'update',
        };

        $eventId = $updateId !== null
            ? "{$channel->id}:{$updateId}"
            : "{$channel->id}:".hash('sha256', $request->getContent());

        $payloadWithChannel = array_merge($payload, ['channel_id' => $channel->id]);

        $event = OmnichatWebhookEvent::query()->firstOrCreate(
            [
                'provider' => 'telegram',
                'external_event_id' => $eventId,
            ],
            [
                'workspace_id' => $channel->workspace_id,
                'social_account_id' => null,
                'event_type' => $eventType,
                'payload' => $payloadWithChannel,
                'status' => 'pending',
                'received_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessTelegramOmnichatWebhook::dispatch($event);
        }

        return response()->noContent();
    }
}
