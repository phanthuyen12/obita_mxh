<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omnichat_channels', function (Blueprint $table): void {
            $table->string('public_key_hash', 64)->nullable()->unique();
        });

        Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->nullable()->after('social_account_id')->constrained('omnichat_channels')->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->nullable()->change();
            $table->unique(['channel_id', 'external_id']);
        });

        Schema::table('omnichat_conversations', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->nullable()->after('social_account_id')->constrained('omnichat_channels')->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->nullable()->change();
            $table->unique(['channel_id', 'external_id']);
            $table->index(['workspace_id', 'channel_id', 'status', 'last_message_at'], 'omnichat_conversations_webchat_index');
        });

        Schema::table('omnichat_messages', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->nullable()->after('social_account_id')->constrained('omnichat_channels')->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->nullable()->change();
            $table->unique(['channel_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('omnichat_messages', function (Blueprint $table): void {
            $table->dropUnique(['channel_id', 'external_id']);
            $table->dropConstrainedForeignId('channel_id');
            $table->foreignUuid('social_account_id')->nullable(false)->change();
        });

        Schema::table('omnichat_conversations', function (Blueprint $table): void {
            $table->dropIndex('omnichat_conversations_webchat_index');
            $table->dropUnique(['channel_id', 'external_id']);
            $table->dropConstrainedForeignId('channel_id');
            $table->foreignUuid('social_account_id')->nullable(false)->change();
        });

        Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
            $table->dropUnique(['channel_id', 'external_id']);
            $table->dropConstrainedForeignId('channel_id');
            $table->foreignUuid('social_account_id')->nullable(false)->change();
        });

        Schema::table('omnichat_channels', function (Blueprint $table): void {
            $table->dropColumn('public_key_hash');
        });
    }
};
