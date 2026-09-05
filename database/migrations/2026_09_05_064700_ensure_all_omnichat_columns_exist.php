<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. omnichat_contacts
        if (Schema::hasTable('omnichat_contacts')) {
            Schema::table('omnichat_contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contacts', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'email')) {
                    $table->string('email')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'phone')) {
                    $table->string('phone', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'avatar_url')) {
                    $table->text('avatar_url')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'is_lead')) {
                    $table->boolean('is_lead')->default(false)->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_status')) {
                    $table->string('lead_status')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_value')) {
                    $table->decimal('lead_value', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        // 2. omnichat_contact_identities
        if (Schema::hasTable('omnichat_contact_identities')) {
            Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contact_identities', 'contact_id')) {
                    $table->foreignUuid('contact_id')->nullable()->constrained('omnichat_contacts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'provider')) {
                    $table->string('provider')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'display_name')) {
                    $table->string('display_name')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'avatar_url')) {
                    $table->text('avatar_url')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        // 3. omnichat_conversations
        if (Schema::hasTable('omnichat_conversations')) {
            Schema::table('omnichat_conversations', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_conversations', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'contact_id')) {
                    $table->foreignUuid('contact_id')->nullable()->constrained('omnichat_contacts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'status')) {
                    $table->string('status')->default('open')->index();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'priority')) {
                    $table->string('priority')->default('normal')->index();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'assigned_user_id')) {
                    $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'last_message_preview')) {
                    $table->text('last_message_preview')->nullable();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'last_message_at')) {
                    $table->timestamp('last_message_at')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'last_inbound_at')) {
                    $table->timestamp('last_inbound_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'last_outbound_at')) {
                    $table->timestamp('last_outbound_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        // 4. omnichat_messages
        if (Schema::hasTable('omnichat_messages')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_messages', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained()->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'conversation_id')) {
                    $table->foreignUuid('conversation_id')->nullable()->constrained('omnichat_conversations')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'sender_contact_id')) {
                    $table->foreignUuid('sender_contact_id')->nullable()->constrained('omnichat_contacts')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'sender_user_id')) {
                    $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'client_id')) {
                    $table->uuid('client_id')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'external_id')) {
                    $table->string('external_id')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'direction')) {
                    $table->string('direction')->default('inbound')->index();
                }
                if (! Schema::hasColumn('omnichat_messages', 'type')) {
                    $table->string('type')->default('text');
                }
                if (! Schema::hasColumn('omnichat_messages', 'body')) {
                    $table->text('body')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'status')) {
                    $table->string('status')->default('delivered')->index();
                }
                if (! Schema::hasColumn('omnichat_messages', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'provider_payload')) {
                    $table->json('provider_payload')->nullable();
                }
                if (! Schema::hasColumn('omnichat_messages', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No-op to preserve columns safely
    }
};
