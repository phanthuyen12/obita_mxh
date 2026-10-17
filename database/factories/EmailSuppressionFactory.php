<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailCampaign\SuppressionReason;
use App\Models\EmailSuppression;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailSuppression> */
class EmailSuppressionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'reason' => SuppressionReason::Unsubscribed,
        ];
    }
}
