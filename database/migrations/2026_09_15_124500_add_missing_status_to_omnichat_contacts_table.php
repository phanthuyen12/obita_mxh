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
                if (! Schema::hasColumn('omnichat_contacts', 'status')) {
                    $table->string('status')->default('active')->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'locale')) {
                    $table->string('locale')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'display_name')) {
                    $table->string('display_name')->nullable();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'lead_stage')) {
                    $table->string('lead_stage')->default('new')->index();
                }
                if (! Schema::hasColumn('omnichat_contacts', 'phone_detected_at')) {
                    $table->timestamp('phone_detected_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('omnichat_contacts')) {
            Schema::table('omnichat_contacts', function (Blueprint $table): void {
                if (Schema::hasColumn('omnichat_contacts', 'phone_detected_at')) {
                    $table->dropColumn('phone_detected_at');
                }
                if (Schema::hasColumn('omnichat_contacts', 'lead_stage')) {
                    $table->dropColumn('lead_stage');
                }
                if (Schema::hasColumn('omnichat_contacts', 'last_seen_at')) {
                    $table->dropColumn('last_seen_at');
                }
                if (Schema::hasColumn('omnichat_contacts', 'locale')) {
                    $table->dropColumn('locale');
                }
                if (Schema::hasColumn('omnichat_contacts', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
