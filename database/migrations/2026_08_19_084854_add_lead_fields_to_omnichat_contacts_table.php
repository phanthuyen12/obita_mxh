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
            if (! Schema::hasColumn('omnichat_contacts', 'is_lead')) {
                $table->boolean('is_lead')->default(false)->index();
            }
            if (! Schema::hasColumn('omnichat_contacts', 'lead_stage')) {
                $table->string('lead_stage')->default('new')->index();
            }
            if (! Schema::hasColumn('omnichat_contacts', 'phone_detected_at')) {
                $table->timestamp('phone_detected_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('omnichat_contacts', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'is_lead', 'lead_stage']);
            $table->dropColumn(['is_lead', 'lead_stage', 'phone_detected_at']);
        });
    }
};
