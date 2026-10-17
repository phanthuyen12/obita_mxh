<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailCampaign\ImportStatus;
use App\Models\EmailContactList;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailContactList> */
class EmailContactListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'source_filename' => 'contacts.csv',
            'status' => ImportStatus::Completed,
            'total_rows' => 0,
            'imported_count' => 0,
            'invalid_count' => 0,
            'duplicate_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ImportStatus::Pending]);
    }
}
