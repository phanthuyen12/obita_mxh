<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * AI bot settings for the mobile LiveChat profile tab. Settings are stored in
 * each channel's `ai_care` configuration (the same shape consumed by
 * HandlePageAiCareAutoReply), applied in batch to every connected channel of
 * the workspace.
 */
class SettingsController extends Controller
{
    /**
     * Current effective AI settings. The API key is never returned — only
     * whether one has been configured.
     */
    public function show(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('view', $workspace);

        [$aiEnabled, $apiKeySet] = $this->currentSettings($workspace->id);

        return response()->json([
            'ai_enabled' => $aiEnabled,
            'api_key_set' => $apiKeySet,
        ]);
    }

    /**
     * Batch-update the AI auto-reply settings on every active channel.
     */
    public function update(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && ($request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN,
        );

        $validated = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'dify_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $apiKey = trim((string) ($validated['dify_api_key'] ?? ''));
        $accounts = SocialAccount::query()->where('workspace_id', $workspace->id)->where('is_active', true)->get();
        $channels = OmnichatChannel::query()->where('workspace_id', $workspace->id)->get();

        foreach ($accounts as $account) {
            $meta = is_array($account->meta) ? $account->meta : [];
            $meta['ai_care'] = $this->mergeAiCare($meta['ai_care'] ?? [], $validated['ai_enabled'], $apiKey);
            $account->forceFill(['meta' => $meta])->save();
        }

        foreach ($channels as $channel) {
            $settings = is_array($channel->settings) ? $channel->settings : [];
            $settings['ai_care'] = $this->mergeAiCare($settings['ai_care'] ?? [], $validated['ai_enabled'], $apiKey);
            $channel->forceFill(['settings' => $settings])->save();
        }

        [$aiEnabled, $apiKeySet] = $this->currentSettings($workspace->id);

        return response()->json([
            'ai_enabled' => $aiEnabled,
            'api_key_set' => $apiKeySet,
        ]);
    }

    /**
     * @return array{0: bool, 1: bool} [ai_enabled, api_key_set]
     */
    private function currentSettings(string $workspaceId): array
    {
        $aiEnabled = SocialAccount::query()->where('workspace_id', $workspaceId)->where('is_active', true)->get()
            ->contains(fn (SocialAccount $account): bool => (bool) data_get($account->meta, 'ai_care.enabled'))
            || OmnichatChannel::query()->where('workspace_id', $workspaceId)->get()
                ->contains(fn (OmnichatChannel $channel): bool => (bool) data_get($channel->settings, 'ai_care.enabled'));

        $apiKeySet = SocialAccount::query()->where('workspace_id', $workspaceId)->where('is_active', true)->get()
            ->contains(fn (SocialAccount $account): bool => filled(data_get($account->meta, 'ai_care.dify_api_key')))
            || OmnichatChannel::query()->where('workspace_id', $workspaceId)->get()
                ->contains(fn (OmnichatChannel $channel): bool => filled(data_get($channel->settings, 'ai_care.dify_api_key')));

        return [(bool) $aiEnabled, (bool) $apiKeySet];
    }

    /**
     * @param  array<string, mixed>|mixed  $existing
     * @return array<string, mixed>
     */
    private function mergeAiCare(mixed $existing, bool $enabled, string $apiKey): array
    {
        $aiCare = is_array($existing) ? $existing : [];
        $aiCare['enabled'] = $enabled;

        if ($apiKey !== '') {
            $aiCare['dify_api_key'] = $apiKey;
        }

        return $aiCare;
    }
}
