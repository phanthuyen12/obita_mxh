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
        if (! Schema::hasTable('customer_segments')) {
            Schema::create('customer_segments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->default('dynamic'); // dynamic, static
                $table->string('description')->nullable();
                $table->json('rules')->nullable(); // {'has_phone': true, 'inactive_days': 30, 'lead_status': 'new'}
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['workspace_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('broadcast_campaigns')) {
            Schema::create('broadcast_campaigns', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('customer_segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
                $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->nullOnDelete();
                $table->string('name');
                $table->string('trigger_type')->default('manual'); // manual, inactivity_drip, scheduled
                $table->integer('trigger_inactive_days')->nullable(); // e.g. 30, 40
                $table->text('message_template'); // Content with variables: {name}, {phone}, {voucher}
                $table->boolean('ai_spin_enabled')->default(false); // Let AI rephrase text to avoid spam
                $table->string('status')->default('draft'); // draft, scheduled, processing, completed, paused, cancelled
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('delay_seconds')->default(5); // Delay between each message
                $table->json('stats')->nullable(); // {'total': 100, 'sent': 95, 'failed': 5, 'replied': 12}
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['trigger_type', 'trigger_inactive_days', 'status'], 'bc_trigger_status_idx');
            });
        }

        if (! Schema::hasTable('broadcast_messages')) {
            Schema::create('broadcast_messages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('broadcast_campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
                $table->foreignUuid('contact_id')->constrained('omnichat_contacts')->cascadeOnDelete();
                $table->foreignUuid('conversation_id')->nullable()->constrained('omnichat_conversations')->nullOnDelete();
                $table->text('sent_body')->nullable();
                $table->string('status')->default('queued'); // queued, sent, failed, delivered, replied
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();

                $table->index(['broadcast_campaign_id', 'status']);
                $table->index(['workspace_id', 'contact_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_messages');
        Schema::dropIfExists('broadcast_campaigns');
        Schema::dropIfExists('customer_segments');
    }
};
