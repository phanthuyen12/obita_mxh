<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichat_conversations')) {
            return;
        }

        Schema::create('omnichat_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('omnichat_contacts')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['workspace_id', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichat_conversations');
    }
};
