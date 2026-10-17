<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Exceptions\WorkspaceMailNotConfiguredException;
use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Builds a per-workspace SMTP mailer for bulk/marketing email.
 *
 * Campaign mail deliberately never uses the platform's default mailer, so a
 * workspace's sending reputation stays its own problem and cannot damage
 * transactional delivery.
 */
class WorkspaceMailerResolver
{
    public function settingsFor(Workspace $workspace): WorkspaceMailSetting
    {
        $settings = WorkspaceMailSetting::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($settings === null || $settings->host === '' || $settings->from_address === '') {
            throw WorkspaceMailNotConfiguredException::forWorkspace($workspace->id);
        }

        return $settings;
    }

    /**
     * Register the workspace transport on the fly and hand back its mailer.
     */
    public function resolve(Workspace $workspace): Mailer
    {
        $settings = $this->settingsFor($workspace);
        $name = $this->mailerName($workspace);

        // `from` / `reply_to` must live INSIDE the mailer config: MailManager
        // reads them from `mail.mailers.<name>` and only falls back to the
        // global `mail.from` when the mailer does not define its own. Setting
        // `mail.from.<name>` instead would silently send every campaign from
        // the platform's default address.
        config()->set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption,
            'username' => $settings->username,
            'password' => $settings->password,
            'timeout' => null,
            'local_domain' => null,
            'from' => [
                'address' => $settings->from_address,
                'name' => $settings->from_name,
            ],
            'reply_to' => $settings->reply_to ? [
                'address' => $settings->reply_to,
                'name' => $settings->from_name,
            ] : null,
        ]);

        Mail::forgetMailers();

        return Mail::mailer($name);
    }

    public function mailerName(Workspace $workspace): string
    {
        return 'workspace_'.str_replace('-', '', $workspace->id);
    }
}
