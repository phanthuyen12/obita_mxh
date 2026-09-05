<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Models\OmnichatChannel;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatChannel> */
class OmnichatChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'provider' => ChannelProvider::Facebook,
            'external_id' => fake()->uuid(),
            'name' => fake()->company(),
            'access_token' => fake()->sha256(),
            'capabilities' => ['messages'],
            'settings' => [],
            'status' => ChannelStatus::Connected,
            'connected_at' => now(),
        ];
    }

    public function zaloOa(): static
    {
        return $this->provider(ChannelProvider::ZaloOa);
    }

    public function instagram(): static
    {
        return $this->provider(ChannelProvider::Instagram);
    }

    public function tiktok(): static
    {
        return $this->provider(ChannelProvider::TikTok);
    }

    public function shopee(): static
    {
        return $this->provider(ChannelProvider::Shopee);
    }

    public function lazada(): static
    {
        return $this->provider(ChannelProvider::Lazada);
    }

    public function facebook(): static
    {
        return $this->provider(ChannelProvider::Facebook);
    }

    public function website(): static
    {
        $publicKey = 'wc_pk_'.fake()->regexify('[A-Za-z0-9]{32}');

        return $this->provider(ChannelProvider::Website)->state(fn (): array => [
            'access_token' => $publicKey,
            'public_key_hash' => hash('sha256', $publicKey),
            'settings' => ['authorized_origins' => ['https://example.com']],
        ]);
    }

    private function provider(ChannelProvider $provider): static
    {
        return $this->state(fn (): array => ['provider' => $provider]);
    }
}
