<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Exceptions\WorkspaceMailNotConfiguredException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

it('saves smtp settings for the workspace', function (): void {
    $this->actingAs($this->user->fresh())
        ->put(route('app.email-campaigns.mail-settings.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer@example.com',
            'password' => 'bi-mat',
            'from_address' => 'marketing@example.com',
            'from_name' => 'Marketing',
        ])
        ->assertRedirect();

    $settings = WorkspaceMailSetting::query()
        ->where('workspace_id', $this->workspace->id)
        ->firstOrFail();

    expect($settings->host)->toBe('smtp.example.com')
        ->and($settings->password)->toBe('bi-mat');

    // Stored encrypted, not as plain text.
    $raw = DB::table('workspace_mail_settings')->where('id', $settings->id)->value('password');
    expect($raw)->not->toBe('bi-mat');
});

it('keeps the stored password when the field is left blank', function (): void {
    WorkspaceMailSetting::factory()->create([
        'workspace_id' => $this->workspace->id,
        'password' => 'mat-khau-cu',
    ]);

    $this->actingAs($this->user->fresh())
        ->put(route('app.email-campaigns.mail-settings.update'), [
            'host' => 'smtp.moi.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'mailer@example.com',
            'password' => '',
            'from_address' => 'marketing@example.com',
            'from_name' => 'Marketing',
        ])
        ->assertRedirect();

    $settings = WorkspaceMailSetting::query()
        ->where('workspace_id', $this->workspace->id)
        ->firstOrFail();

    expect($settings->host)->toBe('smtp.moi.com')
        ->and($settings->password)->toBe('mat-khau-cu');
});

it('throws rather than falling back to the platform mailer', function (): void {
    expect(fn () => app(WorkspaceMailerResolver::class)->settingsFor($this->workspace))
        ->toThrow(WorkspaceMailNotConfiguredException::class);
});

it('builds a mailer from the workspace settings', function (): void {
    WorkspaceMailSetting::factory()->create([
        'workspace_id' => $this->workspace->id,
        'host' => 'smtp.rieng.com',
        'port' => 2525,
    ]);

    $resolver = app(WorkspaceMailerResolver::class);
    $resolver->resolve($this->workspace);

    $name = $resolver->mailerName($this->workspace);

    expect(config("mail.mailers.{$name}.host"))->toBe('smtp.rieng.com')
        ->and(config("mail.mailers.{$name}.port"))->toBe(2525)
        ->and(config("mail.mailers.{$name}.transport"))->toBe('smtp');
});

it('sends from the workspace domain address, not the platform default', function (): void {
    config(['mail.from' => ['address' => 'no-reply@nentang.test', 'name' => 'Nền tảng']]);

    WorkspaceMailSetting::factory()->create([
        'workspace_id' => $this->workspace->id,
        'from_address' => 'sales@congtycuatoi.com',
        'from_name' => 'Công Ty Của Tôi',
        'reply_to' => 'hotro@congtycuatoi.com',
    ]);

    $resolver = app(WorkspaceMailerResolver::class);
    $resolver->resolve($this->workspace);

    $name = $resolver->mailerName($this->workspace);

    expect(config("mail.mailers.{$name}.from.address"))->toBe('sales@congtycuatoi.com')
        ->and(config("mail.mailers.{$name}.from.name"))->toBe('Công Ty Của Tôi')
        ->and(config("mail.mailers.{$name}.reply_to.address"))->toBe('hotro@congtycuatoi.com');
});

it('blocks a member without the can_email permission from the settings page', function (): void {
    $member = User::factory()->create(['account_id' => $this->user->account_id]);
    $this->workspace->members()->attach($member->id, [
        'role' => Role::Member->value,
        'can_email' => false,
    ]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member->fresh())
        ->get(route('app.email-campaigns.mail-settings.edit'))
        ->assertForbidden();
});
