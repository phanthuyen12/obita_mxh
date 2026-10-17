<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailCampaign\Status;
use App\Models\EmailCampaign;
use App\Models\EmailContactList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailCampaign> */
class EmailCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => EmailContactList::find($attributes['email_contact_list_id'])?->workspace_id,
            'email_contact_list_id' => EmailContactList::factory(),
            'name' => fake()->words(3, true),
            'subject' => fake()->sentence(),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'status' => Status::Draft,
            'delay_seconds' => 1,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => Status::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);
    }
}
