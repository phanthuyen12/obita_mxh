<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichat_messages')) {
            return;
        }

        Schema::create('omnichat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('omnichat_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_contact_id')->nullable()->constrained('omnichat_contacts')->nullOnDelete();
            $table->string('external_id');
            $table->string('direction')->index();
            $table->string('type');
            $table->text('body')->nullable();
            $table->string('status')->index();
            $table->json('provider_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['conversation_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichat_messages');
    }
};
