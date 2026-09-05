<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatContact;
use App\Models\OmnichatContactIdentity;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatContactIdentity> */
class OmnichatContactIdentityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => fn (array $attributes) => SocialAccount::find($attributes['social_account_id'])?->workspace_id,
            'contact_id' => OmnichatContact::factory(),
            'social_account_id' => SocialAccount::factory()->facebook(),
            'provider' => 'facebook',
            'external_id' => fake()->unique()->numerify('###############'),
            'meta' => [],
        ];
    }
}
