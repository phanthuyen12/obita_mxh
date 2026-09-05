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
        Schema::create('post_metric_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_platform_id');
            $table->json('metrics');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->foreign('post_platform_id')->references('id')->on('post_platforms')->cascadeOnDelete();
            $table->unique(['post_platform_id', 'captured_at']);
            $table->index(['post_platform_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_metric_snapshots');
    }
};
