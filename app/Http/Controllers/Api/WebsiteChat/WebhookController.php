<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebsiteChat;

use App\Enums\Omnichat\ChannelProvider;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\WebsiteChat\UpdateWebhookRequest;
use App\Models\OmnichatChannel;
use App\Services\Brand\SafeHttpFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function show(Request $request, OmnichatChannel $channel): JsonResponse
    {
        $this->ensureWebsiteChannelBelongsToWorkspace($request, $channel);

        return response()->json(['webhook' => $this->configuration($channel)]);
    }

    public function update(UpdateWebhookRequest $request, OmnichatChannel $channel, SafeHttpFetcher $safeHttpFetcher): JsonResponse
    {
        $this->ensureWebsiteChannelBelongsToWorkspace($request, $channel);
        try {
            $safeHttpFetcher->guardAgainstSsrf($request->validated('url'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }

        $settings = $channel->settings ?? [];
        $secret = $channel->webhook_secret;
        if ($secret === null || $request->boolean('rotate_secret')) {
            $secret = 'whsec_'.Str::random(48);
        }

        $settings['outbound_webhook'] = [
            'url' => $request->validated('url'),
            'events' => array_values(array_unique($request->validated('events'))),
            'enabled' => true,
        ];
        $channel->update(['settings' => $settings, 'webhook_secret' => $secret]);

        return response()->json(['webhook' => $this->configuration($channel->refresh()), 'secret' => $secret]);
    }

    public function destroy(Request $request, OmnichatChannel $channel): JsonResponse
    {
        $this->ensureWebsiteChannelBelongsToWorkspace($request, $channel);

        $settings = $channel->settings ?? [];
        unset($settings['outbound_webhook']);
        $channel->update(['settings' => $settings, 'webhook_secret' => null]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureWebsiteChannelBelongsToWorkspace(Request $request, OmnichatChannel $channel): void
    {
        abort_unless(
            $channel->workspace_id === $request->user()->current_workspace_id
            && $channel->provider === ChannelProvider::Website,
            Response::HTTP_NOT_FOUND,
        );
    }

    /** @return array{url: ?string, events: array<int, string>, enabled: bool} */
    private function configuration(OmnichatChannel $channel): array
    {
        return [
            'url' => data_get($channel->settings, 'outbound_webhook.url'),
            'events' => data_get($channel->settings, 'outbound_webhook.events', []),
            'enabled' => (bool) data_get($channel->settings, 'outbound_webhook.enabled', false),
        ];
    }
}
