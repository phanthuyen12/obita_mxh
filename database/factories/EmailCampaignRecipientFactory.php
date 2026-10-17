<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailCampaign\RecipientStatus;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EmailCampaignRecipient> */
class EmailCampaignRecipientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => EmailCampaign::find($attributes['email_campaign_id'])?->workspace_id,
            'email_campaign_id' => EmailCampaign::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => RecipientStatus::Queued,
            'unsubscribe_token' => Str::random(48),
        ];
    }
}
