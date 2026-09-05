<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichat_channels')) {
            Schema::table('omnichat_channels', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_channels', 'public_key_hash')) {
                    $table->string('public_key_hash', 64)->nullable()->unique();
                }
            });
        }

        if (Schema::hasTable('omnichat_contact_identities')) {
            Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contact_identities', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                } else {
                    $table->foreignUuid('social_account_id')->nullable()->change();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasIndex('omnichat_contact_identities', ['channel_id', 'external_id'])) {
                    $table->unique(['channel_id', 'external_id']);
                }
            });
        }

        if (Schema::hasTable('omnichat_conversations')) {
            Schema::table('omnichat_conversations', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_conversations', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                } else {
                    $table->foreignUuid('social_account_id')->nullable()->change();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasIndex('omnichat_conversations', ['channel_id', 'external_id'])) {
                    $table->unique(['channel_id', 'external_id']);
                }
                if (! Schema::hasIndex('omnichat_conversations', 'omnichat_conversations_webchat_index')) {
                    $table->index(['workspace_id', 'channel_id', 'status', 'last_message_at'], 'omnichat_conversations_webchat_index');
                }
            });
        }

        if (Schema::hasTable('omnichat_messages')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_messages', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                } else {
                    $table->foreignUuid('social_account_id')->nullable()->change();
                }
                if (! Schema::hasColumn('omnichat_messages', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasIndex('omnichat_messages', ['channel_id', 'external_id'])) {
                    $table->unique(['channel_id', 'external_id']);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('omnichat_messages')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                if (Schema::hasIndex('omnichat_messages', ['channel_id', 'external_id'])) {
                    $table->dropUnique(['channel_id', 'external_id']);
                }
                if (Schema::hasColumn('omnichat_messages', 'channel_id')) {
                    $table->dropConstrainedForeignId('channel_id');
                }
                if (Schema::hasColumn('omnichat_messages', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable(false)->change();
                }
            });
        }

        if (Schema::hasTable('omnichat_conversations')) {
            Schema::table('omnichat_conversations', function (Blueprint $table): void {
                if (Schema::hasIndex('omnichat_conversations', 'omnichat_conversations_webchat_index')) {
                    $table->dropIndex('omnichat_conversations_webchat_index');
                }
                if (Schema::hasIndex('omnichat_conversations', ['channel_id', 'external_id'])) {
                    $table->dropUnique(['channel_id', 'external_id']);
                }
                if (Schema::hasColumn('omnichat_conversations', 'channel_id')) {
                    $table->dropConstrainedForeignId('channel_id');
                }
                if (Schema::hasColumn('omnichat_conversations', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable(false)->change();
                }
            });
        }

        if (Schema::hasTable('omnichat_contact_identities')) {
            Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
                if (Schema::hasIndex('omnichat_contact_identities', ['channel_id', 'external_id'])) {
                    $table->dropUnique(['channel_id', 'external_id']);
                }
                if (Schema::hasColumn('omnichat_contact_identities', 'channel_id')) {
                    $table->dropConstrainedForeignId('channel_id');
                }
                if (Schema::hasColumn('omnichat_contact_identities', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable(false)->change();
                }
            });
        }

        if (Schema::hasTable('omnichat_channels') && Schema::hasColumn('omnichat_channels', 'public_key_hash')) {
            Schema::table('omnichat_channels', function (Blueprint $table): void {
                $table->dropColumn('public_key_hash');
            });
        }
    }
};
