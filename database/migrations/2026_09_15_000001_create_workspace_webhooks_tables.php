<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_webhooks')) {
            Schema::create('workspace_webhooks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('name');
                $table->text('url');
                $table->string('secret', 64);
                $table->json('events');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['workspace_id', 'is_active'], 'wh_workspace_active_idx');
            });
        }

        if (! Schema::hasTable('workspace_webhook_deliveries')) {
            Schema::create('workspace_webhook_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_webhook_id')->constrained('workspace_webhooks')->cascadeOnDelete();
                $table->string('event');
                $table->json('payload');
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('status', 20)->default('pending'); // pending, success, failed
                $table->unsignedTinyInteger('attempts')->default(1);
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['workspace_webhook_id', 'created_at'], 'wh_deliveries_wh_created_idx');
                $table->index(['status', 'created_at'], 'wh_deliveries_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_webhook_deliveries');
        Schema::dropIfExists('workspace_webhooks');
    }
};
