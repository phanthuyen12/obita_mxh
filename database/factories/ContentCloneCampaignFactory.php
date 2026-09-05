<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentCloneCampaign;
use App\Models\ContentWorkflow;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentCloneCampaign>
 */
class ContentCloneCampaignFactory extends Factory
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
            'source_post_id' => Post::factory(),
            'content_workflow_id' => ContentWorkflow::factory(),
            'created_by' => User::factory(),
            'target_social_account_ids' => [],
            'theme' => 'Nội dung giáo dục',
            'prompt' => null,
            'initial_content' => null,
            'total_posts' => 7,
            'generated_posts' => 0,
            'interval_days' => 1,
            'start_at' => now()->addHour(),
            'next_run_at' => now()->addHour(),
            'require_approval' => true,
            'is_active' => true,
            'last_error' => null,
        ];
    }
}
