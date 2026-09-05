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
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table): void {
                if (! Schema::hasColumn('posts', 'is_ceo_content')) {
                    $table->boolean('is_ceo_content')->default(false)->after('status')->index();
                }
                if (! Schema::hasColumn('posts', 'topic_tags')) {
                    $table->json('topic_tags')->nullable()->after('is_ceo_content');
                }
            });
        }

        if (! Schema::hasTable('page_follower_snapshots')) {
            Schema::create('page_follower_snapshots', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('social_account_id');
                $table->unsignedBigInteger('follower_count')->default(0);
                $table->date('date');
                $table->timestamp('captured_at');
                $table->timestamps();

                $table->foreign('social_account_id')->references('id')->on('social_accounts')->cascadeOnDelete();
                $table->unique(['social_account_id', 'date']);
                $table->index(['social_account_id', 'date']);
            });
        }

        if (! Schema::hasTable('workspace_kpi_targets')) {
            Schema::create('workspace_kpi_targets', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('social_account_id')->nullable();
                $table->string('period_type')->default('week'); // week, month
                $table->string('period_key'); // e.g. 2026-W34, 2026-08
                $table->unsignedInteger('target_posts_count')->default(0);
                $table->unsignedInteger('target_ceo_posts_count')->default(0);
                $table->timestamps();

                $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
                $table->foreign('social_account_id')->references('id')->on('social_accounts')->nullOnDelete();
                $table->unique(['workspace_id', 'social_account_id', 'period_type', 'period_key'], 'workspace_kpi_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_kpi_targets');
        Schema::dropIfExists('page_follower_snapshots');
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['is_ceo_content']);
            $table->dropColumn(['is_ceo_content', 'topic_tags']);
        });
    }
};
