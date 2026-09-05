<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'type' => 'master',
            'created_by' => User::factory(),
            'is_locked' => false,
            'sort_order' => 0,
        ];
    }

    public function personal(?Folder $parent = null): static
    {
        return $this->state(function () use ($parent): array {
            $parent ??= Folder::factory()->create();

            return [
                'workspace_id' => $parent->workspace_id,
                'parent_id' => $parent->id,
                'master_folder_id' => $parent->master_folder_id ?? $parent->id,
                'type' => 'personal',
            ];
        });
    }
}
