<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\Omnichat\ProcessFacebookMessengerWebhook;
use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FacebookMessengerWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $verifyToken = (string) config('services.facebook.webhook_verify_token');

        abort_unless(
            $mode === 'subscribe'
                && $verifyToken !== ''
                && is_string($token)
                && hash_equals($verifyToken, $token)
                && is_scalar($challenge),
            SymfonyResponse::HTTP_FORBIDDEN,
        );

        return response((string) $challenge, SymfonyResponse::HTTP_OK)
            ->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Facebook Messenger webhook signature rejected', ['ip' => $request->ip()]);
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();

        abort_unless(data_get($payload, 'object') === 'page', SymfonyResponse::HTTP_NOT_FOUND);
        foreach (data_get($payload, 'entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $pageId = data_get($entry, 'id');

            if (! is_string($pageId) || $pageId === '') {
                continue;
            }

            $socialAccount = SocialAccount::query()
                ->where('platform', Platform::Facebook)
                ->where('platform_user_id', $pageId)
                ->first();

            if ($socialAccount === null) {
                Log::warning('Facebook Messenger webhook Page is not connected', ['page_id' => $pageId]);

                continue;
            }

            foreach (data_get($entry, 'messaging', []) as $messagingEvent) {
                if (! is_array($messagingEvent)) {
                    continue;
                }

                $messageId = data_get($messagingEvent, 'message.mid');

                if (! is_string($messageId) || $messageId === '') {
                    continue;
                }

                $webhookEvent = OmnichatWebhookEvent::query()->firstOrCreate(
                    [
                        'provider' => 'facebook',
                        'external_event_id' => $pageId.':'.$messageId,
                    ],
                    [
                        'workspace_id' => $socialAccount->workspace_id,
                        'social_account_id' => $socialAccount->id,
                        'event_type' => data_get($messagingEvent, 'message.is_echo') === true
                            ? 'message_echo'
                            : 'message',
                        'payload' => [
                            'entry_id' => $pageId,
                            'entry_time' => data_get($entry, 'time'),
                            'messaging' => $messagingEvent,
                        ],
                        'status' => 'pending',
                        'received_at' => now(),
                    ],
                );

                if ($webhookEvent->wasRecentlyCreated) {
                    ProcessFacebookMessengerWebhook::dispatch($webhookEvent);
                }
            }
        }

        return response('EVENT_RECEIVED', SymfonyResponse::HTTP_OK)
            ->header('Content-Type', 'text/plain');
    }

    private function hasValidSignature(Request $request): bool
    {
        $appSecret = (string) config('services.facebook.client_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($appSecret === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
