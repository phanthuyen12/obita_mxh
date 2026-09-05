<?php

declare(strict_types=1);

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
            $table->string('ai_logo_path')->nullable()->after('ai_image_style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->dropColumn('ai_logo_path');
        });
    }
};
