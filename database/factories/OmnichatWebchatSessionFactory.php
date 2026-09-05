<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatWebchatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmnichatWebchatSession>
 */
class OmnichatWebchatSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', fake()->uuid()),
            'visitor_id_hash' => hash('sha256', fake()->uuid()),
            'origin' => 'https://example.com',
            'locale' => 'vi',
            'context' => [],
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
