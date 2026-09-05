<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Models\OmnichatChannel;
use App\Models\OmnichatWebchatSession;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WebsiteChatService
{
    /** @param array<string, mixed> $data */
    public function createChannel(Workspace $workspace, array $data): OmnichatChannel
    {
        $publicKey = $this->newPublicKey();

        return OmnichatChannel::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => ChannelProvider::Website,
            'external_id' => (string) Str::uuid(),
            'name' => $data['name'],
            'access_token' => $publicKey,
            'public_key_hash' => hash('sha256', $publicKey),
            'capabilities' => ['messages', 'realtime'],
            'settings' => $this->settings($data),
            'status' => ChannelStatus::Connected,
            'connected_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateChannel(OmnichatChannel $channel, array $data): OmnichatChannel
    {
        $channel->update(['name' => $data['name'], 'settings' => $this->settings($data)]);

        return $channel->refresh();
    }

    public function rotatePublicKey(OmnichatChannel $channel): string
    {
        $publicKey = $this->newPublicKey();
        $channel->update([
            'access_token' => $publicKey,
            'public_key_hash' => hash('sha256', $publicKey),
        ]);

        $channel->webchatSessions()->whereNull('ended_at')->update(['ended_at' => now()]);

        return $publicKey;
    }

    public function channelFromRequest(Request $request): ?OmnichatChannel
    {
        $publicKey = $request->header('X-Website-Chat-Key', $request->query('public_key'));

        if (! is_string($publicKey) || ! Str::startsWith($publicKey, 'wc_pk_')) {
            return null;
        }

        return OmnichatChannel::query()
            ->where('public_key_hash', hash('sha256', $publicKey))
            ->where('provider', ChannelProvider::Website)
            ->connected()
            ->first();
    }

    public function sessionFromRequest(Request $request): ?OmnichatWebchatSession
    {
        $token = $request->bearerToken();

        if ($token === null || ! Str::startsWith($token, 'wc_session_')) {
            return null;
        }

        return OmnichatWebchatSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->with(['channel', 'conversation.contact'])
            ->first();
    }

    public function requestOrigin(Request $request): ?string
    {
        $origin = $request->header('Origin')
            ?? $request->header('X-Website-Chat-Origin')
            ?? $request->header('Referer');

        return is_string($origin) ? $this->normalizeOrigin($origin) : null;
    }

    public function originIsAllowed(OmnichatChannel $channel, ?string $origin): bool
    {
        if ($origin === null) {
            return false;
        }

        return in_array($origin, Arr::get($channel->settings ?? [], 'authorized_origins', []), true);
    }

    public function normalizeOrigin(string $origin): ?string
    {
        $parts = parse_url(trim($origin));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $normalized = Str::lower($parts['scheme'].'://'.$parts['host']);
        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function settings(array $data): array
    {
        return [
            'authorized_origins' => collect($data['authorized_origins'])
                ->map(fn (string $origin): ?string => $this->normalizeOrigin($origin))
                ->filter()->unique()->values()->all(),
            'welcome_message' => $data['welcome_message'],
            'offline_message' => $data['offline_message'],
            'primary_color' => Str::upper($data['primary_color']),
            'position' => $data['position'],
            'privacy_url' => $data['privacy_url'] ?? null,
        ];
    }

    private function newPublicKey(): string
    {
        return 'wc_pk_'.Str::random(48);
    }
}
