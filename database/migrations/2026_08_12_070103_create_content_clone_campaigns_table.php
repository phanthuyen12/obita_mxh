<?php

declare(strict_types=1);

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
        Schema::create('content_clone_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('content_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->json('target_social_account_ids');
            $table->string('theme')->nullable();
            $table->text('prompt')->nullable();
            $table->unsignedInteger('total_posts');
            $table->unsignedInteger('generated_posts')->default(0);
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->dateTime('start_at');
            $table->dateTime('next_run_at');
            $table->boolean('require_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_clone_campaigns');
    }
};
