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
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->string('ai_image_resolution')->nullable()->after('ai_image_style');
            $table->string('ai_image_aspect_ratio')->nullable()->after('ai_image_resolution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ai_image_resolution', 'ai_image_aspect_ratio']);
        });
    }
};
