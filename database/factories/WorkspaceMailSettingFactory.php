<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceMailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkspaceMailSetting> */
class WorkspaceMailSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer@example.com',
            'password' => 'secret',
            'from_address' => 'marketing@example.com',
            'from_name' => 'Marketing',
        ];
    }
}
