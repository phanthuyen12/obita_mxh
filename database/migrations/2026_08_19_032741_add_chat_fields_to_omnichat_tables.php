<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omnichat_contacts')) {
            Schema::table('omnichat_contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_contacts', 'email')) {
                    $table->string('email')->nullable()->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'phone')) {
                    $table->string('phone', 32)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('omnichat_messages')) {
            Schema::table('omnichat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('omnichat_messages', 'sender_user_id')) {
                    $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('omnichat_messages', 'client_id')) {
                    $table->uuid('client_id')->nullable();
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
            });
        }
    }

    public function down(): void
    {
        Schema::table('omnichat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sender_user_id');
            $table->dropColumn(['client_id', 'delivered_at', 'read_at', 'failed_at', 'error_message']);
        });

        Schema::table('omnichat_contacts', function (Blueprint $table): void {
            $table->dropColumn(['email', 'phone']);
        });
    }
};
