<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omnichat_webchat_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('omnichat_channels')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('omnichat_conversations')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('visitor_id_hash', 64)->nullable();
            $table->string('origin');
            $table->string('locale', 12)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'visitor_id_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichat_webchat_sessions');
    }
};
