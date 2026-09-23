<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Per-channel AI bot configuration for the mobile LiveChat profile tab.
 *
 * - Admins (owner / manageTeam) may assign which Dify bot answers on a page
 *   or Telegram channel (`bot_id`).
 * - Any member with omnichat access (sales) may switch AI on/off per channel
 *   (`ai_enabled`) without needing management rights.
 */
class ChannelAiController extends Controller
{
    /**
     * AI state of every channel connected to LiveChat.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $rows = collect();

        SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->get()
            ->each(fn (SocialAccount $account) => $rows->push([
                'id' => $account->id,
                'name' => $account->display_label,
                'provider' => $account->platform->network(),
                'type' => 'social_account',
                'ai_enabled' => (bool) data_get($account->meta, 'ai_care.enabled', false),
                'bot_id' => data_get($account->meta, 'ai_care.bot_id'),
            ]));

        OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('provider', [\App\Enums\Omnichat\ChannelProvider::Telegram, \App\Enums\Omnichat\ChannelProvider::Website])
            ->get()
            ->each(fn (OmnichatChannel $channel) => $rows->push([
                'id' => $channel->id,
                'name' => $channel->name,
                'provider' => $channel->provider->value,
                'type' => 'channel',
                'ai_enabled' => (bool) data_get($channel->settings, 'ai_care.enabled', false),
                'bot_id' => data_get($channel->settings, 'ai_care.bot_id'),
            ]));

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * Update the AI state of one channel. Assigning a bot requires
     * management rights; flipping AI on/off does not.
     */
    public function update(Request $request, string $channel): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        $wantsBotAssignment = $request->has('bot_id');
        if ($wantsBotAssignment) {
            abort_unless(
                $request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace),
                SymfonyResponse::HTTP_FORBIDDEN,
            );
        }

        $validated = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'bot_id' => ['nullable', 'uuid', Rule::exists('ai_bots', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $botId = $wantsBotAssignment ? ($validated['bot_id'] ?? null) : null;

        $account = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($channel)
            ->first();

        if ($account !== null) {
            $meta = is_array($account->meta) ? $account->meta : [];
            $meta['ai_care'] = $this->mergeAiCare($meta['ai_care'] ?? [], $validated['ai_enabled'], $wantsBotAssignment, $botId);
            $account->forceFill(['meta' => $meta])->save();

            return $this->channelResponse($account->meta['ai_care'] ?? []);
        }

        $omnichatChannel = OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($channel)
            ->first();
        abort_unless($omnichatChannel !== null, 404);

        $settings = is_array($omnichatChannel->settings) ? $omnichatChannel->settings : [];
        $settings['ai_care'] = $this->mergeAiCare($settings['ai_care'] ?? [], $validated['ai_enabled'], $wantsBotAssignment, $botId);
        $omnichatChannel->forceFill(['settings' => $settings])->save();

        return $this->channelResponse($settings['ai_care']);
    }

    /**
     * @param  array<string, mixed>|mixed  $existing
     * @return array<string, mixed>
     */
    private function mergeAiCare(mixed $existing, bool $enabled, bool $withBot, ?string $botId): array
    {
        $aiCare = is_array($existing) ? $existing : [];
        $aiCare['enabled'] = $enabled;

        // Only touch the assignment when the caller explicitly sent it —
        // sales toggling AI on/off must not wipe the admin's bot choice.
        if ($withBot) {
            if ($botId !== null) {
                $aiCare['bot_id'] = $botId;
            } else {
                unset($aiCare['bot_id']);
            }
        }

        return $aiCare;
    }

    /**
     * @param  array<string, mixed>  $aiCare
     */
    private function channelResponse(array $aiCare): JsonResponse
    {
        return response()->json([
            'ai_enabled' => (bool) ($aiCare['enabled'] ?? false),
            'bot_id' => $aiCare['bot_id'] ?? null,
        ]);
    }
}
