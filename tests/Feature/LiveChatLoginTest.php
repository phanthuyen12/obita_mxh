<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config(['trypost.self_hosted' => true]);
});

it('renders the dedicated livechat login page for guests', function (): void {
    $this->get(route('login.livechat'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/LoginLiveChat')
            ->where('redirect', '/omnichat/livechat'));
});

it('redirects authenticated users straight to livechat', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user->fresh())
        ->get(route('login.livechat'))
        ->assertRedirect('/omnichat/livechat');
});

it('accepts the shared login credentials and lands on livechat', function (): void {
    $user = User::factory()->create([
        'email' => 'livechat@example.com',
        'password' => Hash::make('secret-password'),
    ]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->withoutMiddleware(EnsureEmailIsVerified::class)
        ->from(route('login.livechat'))
        ->post(route('login.store'), [
            'email' => 'livechat@example.com',
            'password' => 'secret-password',
            'redirect' => '/omnichat/livechat',
        ])
        ->assertRedirect('/omnichat/livechat');

    $this->assertAuthenticatedAs($user->fresh());
});

it('keeps the redirect internal-only', function (): void {
    $user = User::factory()->create([
        'email' => 'livechat2@example.com',
        'password' => Hash::make('secret-password'),
    ]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->from(route('login.livechat'))
        ->post(route('login.store'), [
            'email' => 'livechat2@example.com',
            'password' => 'secret-password',
            'redirect' => 'https://evil.example.com',
        ])
        ->assertRedirect(route('app.calendar'));
});
