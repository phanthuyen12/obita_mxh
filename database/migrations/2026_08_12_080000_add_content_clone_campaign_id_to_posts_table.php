<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->foreignUuid('content_clone_campaign_id')
                ->nullable()
                ->after('content_workflow_id')
                ->constrained('content_clone_campaigns')
                ->nullOnDelete();

            $table->index(['content_clone_campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_clone_campaign_id');
            $table->dropIndex(['content_clone_campaign_id', 'created_at']);
        });
    }
};
