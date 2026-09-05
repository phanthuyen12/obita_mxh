<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omnichat_contacts', function (Blueprint $table): void {
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
        });

        Schema::table('omnichat_messages', function (Blueprint $table): void {
            $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unique(['conversation_id', 'client_id']);
        });
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
