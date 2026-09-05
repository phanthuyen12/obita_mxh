<?php

namespace Database\Factories;

use App\Models\ContentWorkflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentWorkflow>
 */
class ContentWorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'workspace_id' => Workspace::factory(),
            'is_active' => true,
        ];
    }
}
