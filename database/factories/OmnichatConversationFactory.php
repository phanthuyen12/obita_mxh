<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatConversation> */
class OmnichatConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => SocialAccount::find($attributes['social_account_id'])?->workspace_id,
            'social_account_id' => SocialAccount::factory()->facebook(),
            'contact_id' => OmnichatContact::factory(),
            'external_id' => fake()->unique()->numerify('###############'),
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
            'meta' => [],
        ];
    }
}
