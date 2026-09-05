<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatContact;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatContact> */
class OmnichatContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'display_name' => fake()->name(),
            'status' => 'active',
            'meta' => [],
            'last_seen_at' => now(),
        ];
    }
}
