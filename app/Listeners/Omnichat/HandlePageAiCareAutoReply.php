<?php

declare(strict_types=1);

namespace App\Listeners\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Events\OmnichatMessageCreated;
use App\Exceptions\DifyConversationNotFoundException;
use App\Models\AiBot;
use App\Models\OmnichatChannel;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Services\Dify\DifyChatClient;
use App\Support\Omnichat\FacebookMessengerClient;
use App\Support\Omnichat\LazadaClient;
use App\Support\Omnichat\ShopeeClient;
use App\Support\Omnichat\TelegramOmnichatClient;
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
        private readonly TelegramOmnichatClient $telegramOmnichatClient,
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

        $message->loadMissing(['conversation.socialAccount', 'conversation.channel', 'senderContact']);
        $conversation = $message->conversation;
        $account = $conversation?->socialAccount;
        $channel = $conversation?->channel;

        if ($conversation === null || ($account === null && $channel === null)) {
            Log::warning('[AI-Care] Missing conversation, social account or channel for message', ['message_id' => $message->id]);

            return;
        }

        // Check if human handover is currently active
        if ((bool) data_get($conversation->meta, 'ai_paused')) {
            Log::info('[AI-Care] Conversation AI is paused for human agent takeover', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        $aiCare = $account !== null
            ? ($account->meta['ai_care'] ?? [])
            : ($channel->settings['ai_care'] ?? []);

        $isEnabled = filter_var($aiCare['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        Log::info('[AI-Care] AI Care status', [
            'account_id' => $account?->id,
            'channel_id' => $channel?->id,
            'enabled' => $isEnabled,
            'provider' => $aiCare['provider'] ?? 'dify',
            'has_dify_key' => ! empty($aiCare['dify_api_key']),
        ]);

        if (! $isEnabled) {
            Log::info('[AI-Care] AI Care is disabled, aborting');

            return;
        }

        // Check for human handover trigger keywords
        $bodyLower = mb_strtolower(trim((string) $message->body), 'UTF-8');
        if ($bodyLower === '/human' || str_contains($bodyLower, 'gặp nhân viên') || str_contains($bodyLower, 'gặp tư vấn viên') || str_contains($bodyLower, 'cần tư vấn viên')) {
            $meta = $conversation->meta ?? [];
            $meta['ai_paused'] = true;
            $conversation->update(['meta' => $meta]);

            $handoverMsg = $aiCare['handover_message'] ?? 'Dạ em đã chuyển thông tin đến nhân viên tư vấn. Bạn vui lòng đợi trong giây lát, nhân viên sẽ hỗ trợ bạn ngay ạ!';
            $this->sendOutboundReply($conversation, $account, $channel, $handoverMsg);

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
                $this->sendOutboundReply($conversation, $account, $channel, $offHoursMsg);
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

        $this->sendOutboundReply($conversation, $account, $channel, $replyText);
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

        // Bot resolution order: the admin's per-channel bot assignment wins,
        // then a channel-specific key, then the workspace default bot.
        $assignedBotId = $aiCare['bot_id'] ?? null;
        $assignedBot = ! empty($assignedBotId)
            ? AiBot::query()->where('workspace_id', $conversation->workspace_id)->whereKey($assignedBotId)->first()
            : null;

        if ($assignedBot !== null) {
            $difyApiKey = $assignedBot->dify_api_key;
            $difyBaseUrl = $assignedBot->dify_base_url ?: $difyBaseUrl;
        } elseif (empty($difyApiKey)) {
            $defaultBot = AiBot::defaultFor($conversation->workspace_id);
            if ($defaultBot !== null) {
                $difyApiKey = $defaultBot->dify_api_key;
                $difyBaseUrl = $defaultBot->dify_base_url ?: $difyBaseUrl;
            }
        }

        Log::info('[AI-Care] Generating reply via provider', [
            'provider' => $provider,
            'dify_base_url' => $difyBaseUrl,
            'has_api_key' => ! empty($difyApiKey),
        ]);

        if ($provider === 'dify' || ! empty($difyApiKey)) {
            try {
                $difyConvId = data_get($conversation->meta, 'dify_conversation_id');
                $userIdentifier = 'cust-'.($message->sender_contact_id ?? $conversation->external_id ?? 'guest');

                // Restore session state from conversation meta
                $sessionMeta = $conversation->meta ?? [];

                /**
                 * Build Dify inputs matching the bot's START node variables:
                 *   - current_intent      : last detected intent (e.g. "mua_hang", "hoi_gia")
                 *   - current_stage       : funnel stage (e.g. "awareness", "consideration", "decision")
                 *   - lead_status         : contact lead stage or "new"
                 *   - phone               : contact phone number
                 *   - last_question_asked : the previous bot question to maintain conversation flow
                 *   - session_memory      : a short JSON summary of key facts learned this session
                 */
                $inputs = [
                    'current_intent' => (string) data_get($sessionMeta, 'dify_current_intent', ''),
                    'current_stage' => (string) data_get($sessionMeta, 'dify_current_stage', 'awareness'),
                    'lead_status' => (string) ($conversation->contact?->lead_stage ?? data_get($sessionMeta, 'dify_lead_status', 'new')),
                    'phone' => (string) ($conversation->contact?->phone ?? ''),
                    'last_question_asked' => (string) data_get($sessionMeta, 'dify_last_question_asked', ''),
                    'session_memory' => (string) data_get($sessionMeta, 'dify_session_memory', ''),
                ];

                Log::info('[AI-Care] Calling DifyChatClient::sendMessage', [
                    'query' => $message->body,
                    'conversation_id' => $difyConvId,
                    'user' => $userIdentifier,
                    'inputs' => $inputs,
                ]);

                try {
                    $res = $this->difyChatClient->sendMessage(
                        query: (string) $message->body,
                        conversationId: $difyConvId,
                        user: $userIdentifier,
                        inputs: $inputs,
                        apiKey: $difyApiKey,
                        baseUrl: $difyBaseUrl,
                    );
                } catch (DifyConversationNotFoundException $e) {
                    // Stale conversation_id — clear it and retry as a fresh conversation
                    Log::warning('[AI-Care] Stale dify_conversation_id, retrying as new conversation', [
                        'stale_id' => $difyConvId,
                        'conversation_id' => $conversation->id,
                    ]);

                    unset($sessionMeta['dify_conversation_id']);
                    $conversation->update(['meta' => $sessionMeta]);
                    $difyConvId = null;

                    $res = $this->difyChatClient->sendMessage(
                        query: (string) $message->body,
                        conversationId: null,
                        user: $userIdentifier,
                        inputs: $inputs,
                        apiKey: $difyApiKey,
                        baseUrl: $difyBaseUrl,
                    );
                }

                $answer = $res['answer'] ?? null;

                Log::info('[AI-Care] Dify API Response received', [
                    'answer' => $answer,
                    'new_conversation_id' => $res['conversation_id'] ?? null,
                ]);

                // Persist new Dify conversation ID
                $newConvId = $res['conversation_id'] ?? null;
                if ($newConvId && $newConvId !== $difyConvId) {
                    $sessionMeta['dify_conversation_id'] = $newConvId;
                }

                // Persist session state from Dify metadata response so next turn has context
                $difyMeta = $res['metadata'] ?? [];
                if (! empty($difyMeta['current_intent'])) {
                    $sessionMeta['dify_current_intent'] = $difyMeta['current_intent'];
                }
                if (! empty($difyMeta['current_stage'])) {
                    $sessionMeta['dify_current_stage'] = $difyMeta['current_stage'];
                }
                if (! empty($difyMeta['lead_status'])) {
                    $sessionMeta['dify_lead_status'] = $difyMeta['lead_status'];
                }
                if (! empty($difyMeta['last_question_asked'])) {
                    $sessionMeta['dify_last_question_asked'] = $difyMeta['last_question_asked'];
                }
                if (! empty($difyMeta['session_memory'])) {
                    $sessionMeta['dify_session_memory'] = $difyMeta['session_memory'];
                } elseif ($answer) {
                    // Fallback: store last bot reply as minimal session memory
                    $sessionMeta['dify_last_question_asked'] = mb_substr($answer, 0, 300);
                }

                $conversation->update(['meta' => $sessionMeta]);

                return $answer;
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

    private function sendOutboundReply(
        OmnichatConversation $conversation,
        ?SocialAccount $account,
        ?OmnichatChannel $channel,
        string $body,
    ): void {
        $externalId = null;
        $providerPayload = [];

        try {
            if ($account !== null) {
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
            } elseif ($channel !== null && $channel->provider === ChannelProvider::Telegram) {
                if ($conversation->external_id) {
                    $res = $this->telegramOmnichatClient->sendMessage($channel, $conversation->external_id, $body);
                    $externalId = $res['id'] ?? null;
                    $providerPayload = ['telegram' => $res['payload'] ?? []];
                }
            }
        } catch (\Throwable $e) {
            Log::error('[AI-Care] Failed to dispatch AI reply', [
                'conversation_id' => $conversation->id,
                'account_id' => $account?->id,
                'channel_id' => $channel?->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Store Outbound message
        DB::transaction(function () use ($conversation, $account, $channel, $body, $externalId, $providerPayload) {
            $sentAt = now();
            $outbound = OmnichatMessage::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'social_account_id' => $account?->id,
                'channel_id' => $channel?->id,
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
