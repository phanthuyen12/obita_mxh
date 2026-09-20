<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy (social_account_id, external_id) unique constraint on omnichat_messages.
 *
 * Background: The original migration created a unique constraint on (social_account_id, external_id)
 * which worked for Facebook/Instagram (social_account_id is always set). However, Telegram uses
 * channel_id instead — social_account_id is NULL for Telegram messages, causing MySQL to enforce
 * a de-facto unique constraint on external_id alone (NULL ≠ NULL in unique indexes).
 *
 * The correct composite constraint (channel_id, external_id) was added by the
 * 2026_08_25_074132_add_channel_ids_to_omnichat_chat_tables migration. This migration removes
 * the stale legacy one so the two no longer conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the standalone external_id unique (no FK dependency — safe to drop directly)
        if (Schema::hasIndex('omnichat_messages', 'omnichat_messages_external_id_unique')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                $table->dropUnique('omnichat_messages_external_id_unique');
            });
        }

        // Drop composite (social_account_id, external_id) unique.
        // MySQL uses this index to support the social_account_id FK, so we must first
        // add a plain index on social_account_id before dropping the composite unique.
        if (Schema::hasIndex('omnichat_messages', 'omnichat_messages_social_account_id_external_id_unique')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                // Add a plain index on social_account_id to keep the FK happy
                if (! Schema::hasIndex('omnichat_messages', 'omnichat_messages_social_account_id_index')) {
                    $table->index('social_account_id');
                }
            });

            Schema::table('omnichat_messages', function (Blueprint $table): void {
                $table->dropUnique('omnichat_messages_social_account_id_external_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('omnichat_messages', function (Blueprint $table): void {
            if (! Schema::hasIndex('omnichat_messages', 'omnichat_messages_social_account_id_external_id_unique')) {
                $table->unique(['social_account_id', 'external_id']);
            }
        });
    }
};
