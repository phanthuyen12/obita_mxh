<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatMessage> */
class OmnichatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => OmnichatConversation::find($attributes['conversation_id'])?->workspace_id,
            'social_account_id' => fn (array $attributes) => OmnichatConversation::find($attributes['conversation_id'])?->social_account_id,
            'conversation_id' => OmnichatConversation::factory(),
            'external_id' => fake()->unique()->uuid(),
            'direction' => 'inbound',
            'type' => 'text',
            'body' => fake()->sentence(),
            'status' => 'delivered',
            'provider_payload' => [],
            'sent_at' => now(),
        ];
    }
}
