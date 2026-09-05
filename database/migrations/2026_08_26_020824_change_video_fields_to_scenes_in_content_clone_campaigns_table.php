<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'video_start_image',
                'video_end_image',
                'video_duration',
                'video_context_prompt',
                'video_action_prompt',
            ]);
            $table->json('video_scenes')->nullable()->after('ai_content_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->dropColumn('video_scenes');
            $table->string('video_start_image', 2048)->nullable()->after('ai_content_mode');
            $table->string('video_end_image', 2048)->nullable()->after('video_start_image');
            $table->integer('video_duration')->nullable()->after('video_end_image');
            $table->text('video_context_prompt')->nullable()->after('video_duration');
            $table->text('video_action_prompt')->nullable()->after('video_context_prompt');
        });
    }
};
