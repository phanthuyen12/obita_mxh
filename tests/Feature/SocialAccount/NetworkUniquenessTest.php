<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('trypost.self_hosted', false);
    $this->workspace = Workspace::factory()->create();
});

test('allows multiple accounts of the same network', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-b',
    ]);

    expect($second->exists)->toBeTrue();
});

test('allows accounts from platform variants in the same network', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
        'platform_user_id' => 'li-profile',
    ]);

    $page = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedInPage,
        'platform_user_id' => 'li-page',
    ]);

    expect($page->exists)->toBeTrue();
});

test('allows different networks in the same workspace', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
    ]);

    $x = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::X,
        'platform_user_id' => 'x-a',
    ]);

    expect($x->exists)->toBeTrue();
});

test('allows the same network in different workspaces', function () {
    $other = Workspace::factory()->create();

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $other->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
    ]);

    expect($second->exists)->toBeTrue();
});

test('reconnecting the same account via updateOrCreate is allowed', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
        'username' => 'old',
    ]);

    $this->workspace->socialAccounts()->updateOrCreate(
        ['platform' => Platform::Instagram->value, 'platform_user_id' => 'ig-a'],
        ['username' => 'new', 'status' => Status::Connected],
    );

    expect($this->workspace->socialAccounts()->count())->toBe(1)
        ->and($this->workspace->socialAccounts()->first()->username)->toBe('new');
});

test('allows multiple accounts in hosted mode', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-a',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'ig-b',
    ]);

    expect($second->exists)->toBeTrue();
});

test('allows the same external id across platform variants', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'shared-ig-id',
    ]);

    $second = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::InstagramFacebook,
        'platform_user_id' => 'shared-ig-id',
    ]);

    expect($second->exists)->toBeTrue();
});
