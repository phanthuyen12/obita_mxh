<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiBot;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiBot> */
class AiBotFactory extends Factory
{
    protected $model = AiBot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'Bot '.fake()->word(),
            'dify_api_key' => 'app-'.fake()->uuid(),
            'dify_base_url' => 'https://kingai.tnicorporation.com/v1',
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
