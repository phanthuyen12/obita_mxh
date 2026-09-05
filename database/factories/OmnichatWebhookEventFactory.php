<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatWebhookEvent;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatWebhookEvent> */
class OmnichatWebhookEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => SocialAccount::find($attributes['social_account_id'])?->workspace_id,
            'social_account_id' => SocialAccount::factory()->facebook(),
            'provider' => 'facebook',
            'external_event_id' => fake()->unique()->uuid(),
            'event_type' => 'message',
            'payload' => [],
            'status' => 'pending',
            'attempts' => 0,
            'received_at' => now(),
        ];
    }
}
