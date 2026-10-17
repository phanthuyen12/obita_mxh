<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('email_contact_list_id')->nullable()->constrained('email_contact_lists')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->longText('body_html');
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sending, paused, completed, failed, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('delay_seconds')->default(2);
            $table->json('stats')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['status', 'scheduled_at'], 'email_campaigns_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
