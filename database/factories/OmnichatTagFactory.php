<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnichatTag;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OmnichatTag> */
class OmnichatTagFactory extends Factory
{
    protected $model = OmnichatTag::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
