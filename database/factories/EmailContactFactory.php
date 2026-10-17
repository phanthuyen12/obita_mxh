<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailCampaign\ContactStatus;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailContact> */
class EmailContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => EmailContactList::find($attributes['email_contact_list_id'])?->workspace_id,
            'email_contact_list_id' => EmailContactList::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'custom_fields' => [],
            'status' => ContactStatus::Active,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => ['status' => ContactStatus::Unsubscribed]);
    }
}
