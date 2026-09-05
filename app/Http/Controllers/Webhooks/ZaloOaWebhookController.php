<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessZaloOaWebhook;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ZaloOaWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();
        Log::info('Received a Zalo OA webhook', ['payload' => $payload]);
        if ($payload === []) {
            return response('OK');
        }

        if (! $this->hasValidSignature($request, $payload)) {
            Log::warning('Ignored a Zalo OA webhook with an invalid signature');

            return response('OK');
        }

        $oaId = data_get($payload, 'oa_id');
        if (! is_scalar($oaId) || (string) $oaId === '') {
            $eventName = (string) data_get($payload, 'event_name');
            $oaId = str_starts_with($eventName, 'oa_send_')
                ? data_get($payload, 'sender.id')
                : data_get($payload, 'recipient.id');
        }

        abort_unless(is_scalar($oaId) && (string) $oaId !== '', Response::HTTP_BAD_REQUEST);
        $account = SocialAccount::query()
            ->where('platform', Platform::ZaloOa)
            ->where('platform_user_id', (string) $oaId)
            ->first();

        if ($account === null) {
            Log::notice('Zalo OA webhook ignored for an unconnected OA', ['oa_id' => (string) $oaId]);

            return response('OK');
        }

        $eventName = (string) data_get($payload, 'event_name', 'unknown');
        $messageId = data_get($payload, 'message.msg_id', data_get($payload, 'message_id'));
        $eventId = is_scalar($messageId) && (string) $messageId !== ''
            ? (string) $oaId.':'.(string) $messageId.':'.$eventName
            : (string) $oaId.':'.hash('sha256', $request->getContent());

        $event = OmnichatWebhookEvent::query()->firstOrCreate(
            ['provider' => 'zalo-oa', 'external_event_id' => $eventId],
            [
                'workspace_id' => $account->workspace_id,
                'social_account_id' => $account->id,
                'event_type' => $eventName,
                'payload' => $payload,
                'status' => 'pending',
                'received_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessZaloOaWebhook::dispatch($event);
        }

        return response('OK');
    }

    /** @param array<string, mixed> $payload */
    private function hasValidSignature(Request $request, array $payload): bool
    {
        $appId = (string) data_get($payload, 'app_id');
        $timestamp = (string) data_get($payload, 'timestamp');
        $secret = (string) config('services.zalo-oa.oa_secret_key');
        $signature = (string) $request->header('X-ZEvent-Signature');
        if (str_starts_with($signature, 'mac=')) {
            $signature = substr($signature, 4);
        }

        if ($appId === '' || $timestamp === '' || $secret === '' || $signature === '') {
            return false;
        }

        if (! hash_equals((string) config('services.zalo-oa.client_id'), $appId)) {
            return false;
        }

        return hash_equals(hash('sha256', $appId.$request->getContent().$timestamp.$secret), $signature);
    }
}
