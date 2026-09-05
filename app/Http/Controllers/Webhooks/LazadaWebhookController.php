<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessLazadaWebhook;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LazadaWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $signature = strtolower((string) $request->header('Authorization'));
        $expected = hash_hmac('sha256', (string) config('services.lazada.app_key').$request->getContent(), (string) config('services.lazada.app_secret'));
        abort_unless($signature !== '' && hash_equals($expected, $signature), SymfonyResponse::HTTP_FORBIDDEN);

        $payload = $request->json()->all();
        if ((int) data_get($payload, 'message_type') !== 2) {
            return response('OK');
        }

        $sellerId = (string) data_get($payload, 'seller_id');
        $messageId = (string) data_get($payload, 'data.message_id');
        $sessionId = (string) data_get($payload, 'data.session_id');
        abort_unless($sellerId !== '' && $messageId !== '' && $sessionId !== '', SymfonyResponse::HTTP_BAD_REQUEST);

        $account = SocialAccount::query()->where('platform', Platform::Lazada)
            ->where('platform_user_id', $sellerId)->first();
        if ($account === null) {
            return response('OK');
        }

        $event = OmnichatWebhookEvent::query()->firstOrCreate(
            ['provider' => 'lazada', 'external_event_id' => $sessionId.':'.$messageId],
            [
                'workspace_id' => $account->workspace_id,
                'social_account_id' => $account->id,
                'event_type' => 'im_message',
                'payload' => $payload,
                'status' => 'pending',
                'received_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessLazadaWebhook::dispatch($event);
        }

        return response('OK');
    }
}
