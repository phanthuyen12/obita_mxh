<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. omnichat_messages
        if (Schema::hasTable('omnichat_messages')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_messages', 'workspace_id')) {
                    $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
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

            // Backfill workspace_id from conversation if missing
            if (Schema::hasColumn('omnichat_messages', 'workspace_id') && Schema::hasColumn('omnichat_conversations', 'workspace_id')) {
                DB::statement('UPDATE omnichat_messages m JOIN omnichat_conversations c ON m.conversation_id = c.id SET m.workspace_id = c.workspace_id WHERE m.workspace_id IS NULL');
            }
        }

        // 2. omnichat_conversations
        if (Schema::hasTable('omnichat_conversations')) {
            Schema::table('omnichat_conversations', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_conversations', 'workspace_id')) {
                    $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'contact_id')) {
                    $table->foreignUuid('contact_id')->nullable()->constrained('omnichat_contacts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_conversations', 'assigned_user_id')) {
                    $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
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

        // 3. omnichat_contacts
        if (Schema::hasTable('omnichat_contacts')) {
            Schema::table('omnichat_contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contacts', 'workspace_id')) {
                    $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'display_name')) {
                    $table->string('display_name')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'avatar_url')) {
                    $table->text('avatar_url')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'status')) {
                    $table->string('status')->default('active')->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'locale')) {
                    $table->string('locale')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'email')) {
                    $table->string('email')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'phone')) {
                    $table->string('phone', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'meta')) {
                    $table->json('meta')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'is_lead')) {
                    $table->boolean('is_lead')->default(false)->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_stage')) {
                    $table->string('lead_stage')->default('new')->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_status')) {
                    $table->string('lead_status')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_value')) {
                    $table->decimal('lead_value', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'phone_detected_at')) {
                    $table->timestamp('phone_detected_at')->nullable();
                }
            });
        }

        // 4. omnichat_contact_identities
        if (Schema::hasTable('omnichat_contact_identities')) {
            Schema::table('omnichat_contact_identities', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contact_identities', 'workspace_id')) {
                    $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'contact_id')) {
                    $table->foreignUuid('contact_id')->nullable()->constrained('omnichat_contacts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'channel_id')) {
                    $table->foreignUuid('channel_id')->nullable()->constrained('omnichat_channels')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'provider')) {
                    $table->string('provider')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contact_identities', 'external_id')) {
                    $table->string('external_id')->nullable()->index();
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

        // 5. omnichat_webhook_events
        if (Schema::hasTable('omnichat_webhook_events')) {
            Schema::table('omnichat_webhook_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_webhook_events', 'workspace_id')) {
                    $table->foreignUuid('workspace_id')->nullable()->after('id')->constrained('workspaces')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'social_account_id')) {
                    $table->foreignUuid('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'provider')) {
                    $table->string('provider')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'external_event_id')) {
                    $table->string('external_event_id')->nullable();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'event_type')) {
                    $table->string('event_type')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'payload')) {
                    $table->longText('payload')->nullable();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'status')) {
                    $table->string('status')->default('pending')->index();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'attempts')) {
                    $table->unsignedSmallInteger('attempts')->default(0);
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'received_at')) {
                    $table->timestamp('received_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_webhook_events', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No-op to preserve data safely
    }
};
