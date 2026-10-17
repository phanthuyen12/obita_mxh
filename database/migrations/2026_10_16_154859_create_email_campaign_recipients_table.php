<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaign_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignUuid('email_contact_id')->nullable()->constrained('email_contacts')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('queued'); // queued, sent, delivered, opened, clicked, bounced, failed, skipped
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email'], 'email_recipients_campaign_email_unique');
            $table->index(['email_campaign_id', 'status'], 'email_recipients_campaign_status_idx');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
    }
};
