<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table): void {
            $table->string('persona_tone')->default('friendly')->after('name'); // friendly, professional, enthusiastic
            $table->string('bot_role')->default('sales_consultant')->after('persona_tone'); // sales_consultant, cskh, technical
            $table->text('greeting_message')->nullable()->after('bot_role');
            $table->longText('system_prompt')->nullable()->after('greeting_message');
            $table->json('objection_rules')->nullable()->after('system_prompt'); // Quy tắc xử lý khi khách chê đắt, so sánh đối thủ...
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table): void {
            $table->dropColumn([
                'persona_tone',
                'bot_role',
                'greeting_message',
                'system_prompt',
                'objection_rules',
            ]);
        });
    }
};
