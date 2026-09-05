<?php

declare(strict_types=1);

namespace App\Listeners\Omnichat;

use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Services\Dify\DifyChatClient;
use App\Support\Omnichat\FacebookMessengerClient;
use App\Support\Omnichat\LazadaClient;
use App\Support\Omnichat\ShopeeClient;
use App\Support\Omnichat\ZaloOaClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandlePageAiCareAutoReply
{
    public function __construct(
        private readonly DifyChatClient $difyChatClient,
        private readonly FacebookMessengerClient $facebookMessengerClient,
        private readonly ZaloOaClient $zaloOaClient,
        private readonly LazadaClient $lazadaClient,
        private readonly ShopeeClient $shopeeClient,
    ) {}

    public function handle(OmnichatMessageCreated $event): void
    {
        $message = $event->message;

        Log::info('[AI-Care] HandlePageAiCareAutoReply received event', [
            'message_id' => $message->id,
            'direction' => $message->direction,
            'body' => $message->body,
        ]);

        // Only auto-reply to inbound customer messages
        if ($message->direction !== 'inbound') {
            Log::info('[AI-Care] Skipping non-inbound message');

            return;
        }

        // Avoid empty messages
        if (blank($message->body)) {
            Log::info('[AI-Care] Skipping empty message body');

            return;
        }

        // Prevent duplicate processing of the exact same inbound message
        $lockKey = 'ai_care_processing_msg_'.$message->id;
        if (! Cache::add($lockKey, true, 120)) {
            Log::info('[AI-Care] Message is already being processed or replied, skipping duplicate trigger', [
                'message_id' => $message->id,
            ]);

            return;
        }

        $message->loadMissing(['conversation.socialAccount', 'senderContact']);
        $conversation = $message->conversation;
        $account = $conversation?->socialAccount;

        if ($conversation === null || $account === null) {
            Log::warning('[AI-Care] Missing conversation or social account for message', ['message_id' => $message->id]);

            return;
        }

        $aiCare = $account->meta['ai_care'] ?? [];
        $isEnabled = filter_var($aiCare['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        Log::info('[AI-Care] Account AI Care status', [
            'account_id' => $account->id,
            'account_name' => $account->display_name ?: $account->username,
            'enabled' => $isEnabled,
            'raw_enabled' => $aiCare['enabled'] ?? null,
            'provider' => $aiCare['provider'] ?? 'dify',
            'has_dify_key' => ! empty($aiCare['dify_api_key']),
        ]);

        if (! $isEnabled) {
            Log::info('[AI-Care] AI Care is disabled for this page, aborting');

            return;
        }

        // Check operating schedule
        if (! $this->isWithinOperatingHours($aiCare)) {
            Log::info('[AI-Care] Outside operating hours');
            $offHoursBehavior = $aiCare['off_hours_behavior'] ?? 'custom_message';
            if ($offHoursBehavior === 'custom_message') {
                $offHoursMsg = $aiCare['off_hours_message'] ?? 'Dạ xin chào! Hiện tại đang ngoài giờ làm việc, chúng tôi sẽ phản hồi bạn sớm nhất.';
                // Prevent duplicate off-hours reply in a row
                $lastMessage = $conversation->messages()->latest('id')->first();
                if ($lastMessage && $lastMessage->body === $offHoursMsg) {
                    return;
                }
                $this->sendOutboundReply($conversation, $account, $offHoursMsg);
            }

            return;
        }

        // Auto-tag lead keywords
        if ((bool) ($aiCare['auto_tag_leads'] ?? false)) {
            $this->checkAndTagLeadKeywords($message, $aiCare['lead_keywords'] ?? []);
        }

        // Apply configured natural typing response delay
        $delaySeconds = (int) ($aiCare['reply_delay_seconds'] ?? 0);
        if ($delaySeconds > 0 && $delaySeconds <= 30) {
            sleep($delaySeconds);
        }

        // Generate AI reply
        $replyText = $this->generateReply($message, $conversation, $aiCare);

        if (blank($replyText)) {
            Log::warning('[AI-Care] Generated AI reply text is blank');

            return;
        }

        Log::info('[AI-Care] Generated AI reply successfully, sending outbound message', [
            'reply' => $replyText,
        ]);

        $this->sendOutboundReply($conversation, $account, $replyText);
    }

    private function isWithinOperatingHours(array $aiCare): bool
    {
        $hoursConfig = $aiCare['operating_hours'] ?? [];
        $mode = $hoursConfig['mode'] ?? '24/7';

        if ($mode === '24/7') {
            return true;
        }

        $timezone = $hoursConfig['timezone'] ?? 'Asia/Ho_Chi_Minh';
        $now = Carbon::now($timezone);

        $allowedDays = $hoursConfig['days'] ?? [1, 2, 3, 4, 5, 6, 7];
        if (! in_array($now->dayOfWeekIso, $allowedDays, true)) {
            return false;
        }

        $startTime = $hoursConfig['start_time'] ?? '08:00';
        $endTime = $hoursConfig['end_time'] ?? '18:00';

        $currentTime = $now->format('H:i');

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    private function checkAndTagLeadKeywords(OmnichatMessage $message, array $keywords): void
    {
        if (empty($keywords) || $message->senderContact === null) {
            return;
        }

        $text = mb_strtolower((string) $message->body, 'UTF-8');
        foreach ($keywords as $keyword) {
            $cleanKw = mb_strtolower(trim((string) $keyword), 'UTF-8');
            if ($cleanKw !== '' && str_contains($text, $cleanKw)) {
                $message->senderContact->update(['is_lead' => true]);
                Log::info('[AI-Care] Tagged contact as lead due to keyword match', ['keyword' => $cleanKw]);
                break;
            }
        }
    }

    private function generateReply(OmnichatMessage $message, OmnichatConversation $conversation, array $aiCare): ?string
    {
        $provider = $aiCare['provider'] ?? 'dify';
        $difyApiKey = $aiCare['dify_api_key'] ?? null;
        $difyBaseUrl = $aiCare['dify_base_url'] ?: 'https://kingai.tnicorporation.com/v1';

        Log::info('[AI-Care] Generating reply via provider', [
            'provider' => $provider,
            'dify_base_url' => $difyBaseUrl,
            'has_api_key' => ! empty($difyApiKey),
        ]);

        // Default or explicit Dify Provider
        if ($provider === 'dify' || ! empty($difyApiKey)) {
            try {
                $difyConvId = data_get($conversation->meta, 'dify_conversation_id');
                $userIdentifier = 'cust-'.($message->sender_contact_id ?? $conversation->external_id ?? 'guest');

                $inputs = [];
                if (! empty($aiCare['persona'])) {
                    $inputs['persona'] = $aiCare['persona'];
                }
                if (! empty($aiCare['knowledge_base'])) {
                    $inputs['knowledge_base'] = $aiCare['knowledge_base'];
                }

                Log::info('[AI-Care] Calling DifyChatClient::sendMessage', [
                    'query' => $message->body,
                    'conversation_id' => $difyConvId,
                    'user' => $userIdentifier,
                ]);

                $res = $this->difyChatClient->sendMessage(
                    query: (string) $message->body,
                    conversationId: $difyConvId,
                    user: $userIdentifier,
                    inputs: $inputs,
                    apiKey: $difyApiKey,
                    baseUrl: $difyBaseUrl,
                );

                Log::info('[AI-Care] Dify API Response received', [
                    'answer' => $res['answer'] ?? null,
                    'new_conversation_id' => $res['conversation_id'] ?? null,
                ]);

                $newConvId = $res['conversation_id'] ?? null;
                if ($newConvId && $newConvId !== $difyConvId) {
                    $meta = $conversation->meta ?? [];
                    $meta['dify_conversation_id'] = $newConvId;
                    $conversation->update(['meta' => $meta]);
                }

                return $res['answer'] ?? null;
            } catch (\Throwable $e) {
                Log::error('[AI-Care] Dify AutoReply generation failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return null;
            }
        }

        return null;
    }

    private function sendOutboundReply(OmnichatConversation $conversation, SocialAccount $account, string $body): void
    {
        $externalId = null;
        $providerPayload = [];

        try {
            $platform = $account->platform?->value ?? 'facebook';

            Log::info('[AI-Care] Sending outbound reply to social platform', [
                'platform' => $platform,
                'external_id' => $conversation->external_id,
            ]);

            if ($platform === 'facebook' && $conversation->external_id) {
                $res = $this->facebookMessengerClient->sendText($account, $conversation->external_id, $body);
                $externalId = $res['id'] ?? null;
                $providerPayload = ['facebook' => $res['payload'] ?? []];
            } elseif ($platform === 'zalo-oa' && $conversation->external_id) {
                $res = $this->zaloOaClient->sendText($account, $conversation->external_id, $body);
                $externalId = $res['id'] ?? null;
                $providerPayload = ['zalo' => $res['payload'] ?? []];
            } elseif ($platform === 'lazada' && $conversation->external_id) {
                $res = $this->lazadaClient->sendText($account, $conversation->external_id, $body);
                $externalId = (string) data_get($res, 'data.message_id', data_get($res, 'message_id'));
                $providerPayload = ['lazada' => $res];
            } elseif ($platform === 'shopee' && $conversation->external_id) {
                $recipientId = (string) data_get($conversation->meta, 'shopee_recipient_id');
                if ($recipientId !== '') {
                    $res = $this->shopeeClient->sendText($account, $recipientId, $body, $conversation->external_id, (int) data_get($conversation->meta, 'business_type', 0));
                    $externalId = (string) data_get($res, 'response.message_id');
                    $providerPayload = ['shopee' => $res];
                }
            }
        } catch (\Throwable $e) {
            Log::error('[AI-Care] Failed to dispatch AI reply to social platform', [
                'conversation_id' => $conversation->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Store Outbound message
        DB::transaction(function () use ($conversation, $account, $body, $externalId, $providerPayload) {
            $sentAt = now();
            $outbound = OmnichatMessage::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'social_account_id' => $account->id,
                'conversation_id' => $conversation->id,
                'client_id' => (string) Str::uuid(),
                'sender_user_id' => null, // AI Bot
                'external_id' => $externalId ?? (string) Str::uuid(),
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $body,
                'status' => $externalId !== null ? 'sent' : 'pending',
                'provider_payload' => $providerPayload,
                'sent_at' => $sentAt,
            ]);

            $conversation->update([
                'last_message_preview' => $body,
                'last_message_at' => $sentAt,
                'last_outbound_at' => $sentAt,
            ]);

            Log::info('[AI-Care] Outbound message saved and broadcasting', ['message_id' => $outbound->id]);

            rescue(fn () => OmnichatMessageCreated::dispatch($outbound), report: false);
        });
    }
}
