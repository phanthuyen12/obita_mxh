<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiConfiguration;

function accountOwnerWithWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

test('ai settings requires authentication', function (): void {
    $this->get(route('app.ai-settings.edit'))->assertRedirect(route('login'));
});

test('account owner can view ai settings', function (): void {
    [$user] = accountOwnerWithWorkspace();

    $this->actingAs($user)
        ->get(route('app.ai-settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/account/Ai')
            ->where('settings.content_clone_ai_provider', 'openai')
            ->where('settings.dify_api_key_configured', false)
        );
});

test('workspace admin who is not account owner can view ai settings', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $owner->id,
        'account_id' => $owner->account_id,
    ]);

    $admin = User::factory()->create([
        'account_id' => $owner->account_id,
        'current_workspace_id' => $workspace->id,
    ]);
    $workspace->members()->attach($admin->id, ['role' => Role::Admin->value]);

    $this->actingAs($admin)
        ->get(route('app.ai-settings.edit'))
        ->assertSuccessful();
});

test('account owner can update ai settings and secrets are not rendered', function (): void {
    [$user] = accountOwnerWithWorkspace();

    $this->actingAs($user)
        ->put(route('app.ai-settings.update'), [
            'content_clone_ai_provider' => 'dify',
            'ai_text_provider' => 'dify',
            'ai_image_provider' => 'dify',
            'dify_base_url' => 'https://dify.example/v1',
            'dify_api_key' => 'dify-secret',
            'dify_connect_timeout' => 7,
            'dify_timeout' => 180,
            'openai_api_key' => 'openai-secret',
            'anthropic_api_key' => '',
            'gemini_api_key' => 'gemini-secret',
            'openrouter_api_key' => 'openrouter-secret',
            'openai_text_model' => 'gpt-4.1-mini',
            'openai_image_model' => 'gpt-image-1',
            'anthropic_text_model' => 'claude-sonnet-4-5',
            'gemini_text_model' => 'gemini-2.5-flash',
            'gemini_image_model' => 'gemini-2.5-flash-image',
            'openrouter_text_model' => 'openai/gpt-4.1-mini',
            'openrouter_image_model' => '',
        ])
        ->assertRedirect();

    app(AiConfiguration::class)->apply();

    expect(config('content_clone.ai_provider'))->toBe('dify')
        ->and(config('ai.default'))->toBe('dify')
        ->and(config('ai.default_for_images'))->toBe('dify')
        ->and(config('services.dify.api_key'))->toBe('dify-secret')
        ->and(config('ai.providers.openrouter.key'))->toBe('openrouter-secret')
        ->and(config('ai.providers.openai.models.text.default'))->toBe('gpt-4.1-mini')
        ->and(config('ai.providers.openai.models.image.default'))->toBe('gpt-image-1')
        ->and(config('ai.providers.gemini.models.text.default'))->toBe('gemini-2.5-flash')
        ->and(config('ai.providers.openrouter.models.image.default'))->toBeNull()
        ->and(AppSetting::query()->where('key', 'services.dify.api_key')->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('app.ai-settings.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('settings.dify_api_key_configured', true)
            ->where('settings.provider_keys.openai', true)
            ->where('settings.provider_keys.gemini', true)
            ->where('settings.provider_keys.openrouter', true)
            ->where('settings.provider_models.openai.text', 'gpt-4.1-mini')
            ->where('settings.provider_models.openai.image', 'gpt-image-1')
            ->where('settings.provider_models.gemini.text', 'gemini-2.5-flash')
            ->missing('settings.dify_api_key')
        );
});
