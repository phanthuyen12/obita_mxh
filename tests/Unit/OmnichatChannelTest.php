<?php

declare(strict_types=1);

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Models\OmnichatChannel;
use App\Models\Workspace;

it('supports all omnichat providers', function (): void {
    expect(array_column(ChannelProvider::cases(), 'value'))->toBe([
        'zalo-oa', 'instagram', 'tiktok', 'shopee', 'lazada', 'facebook', 'website',
    ]);
});

it('stores encrypted credentials and relationships', function (): void {
    $workspace = Workspace::factory()->create();
    $channel = OmnichatChannel::factory()->zaloOa()->create([
        'workspace_id' => $workspace->id,
        'access_token' => 'secret-access-token',
        'settings' => ['oa_secret_key' => 'secret-key'],
    ]);

    expect($channel->provider)->toBe(ChannelProvider::ZaloOa)
        ->and($channel->status)->toBe(ChannelStatus::Connected)
        ->and($channel->workspace->is($workspace))->toBeTrue()
        ->and($channel->access_token)->toBe('secret-access-token')
        ->and($channel->getRawOriginal('access_token'))->not->toBe('secret-access-token')
        ->and($channel->toArray())->not->toHaveKey('access_token');
});

it('filters connected channels by provider', function (): void {
    $workspace = Workspace::factory()->create();
    OmnichatChannel::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    OmnichatChannel::factory()->shopee()->create(['workspace_id' => $workspace->id]);
    OmnichatChannel::factory()->lazada()->create([
        'workspace_id' => $workspace->id,
        'status' => ChannelStatus::Disabled,
    ]);

    expect(OmnichatChannel::query()->connected()->count())->toBe(2)
        ->and(OmnichatChannel::query()->forProvider(ChannelProvider::Shopee)->count())->toBe(1);
});
