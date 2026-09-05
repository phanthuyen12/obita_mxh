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
            $table->unsignedInteger('ai_image_count')->default(0)->after('prompt');
            $table->boolean('diff_content_per_page')->default(false)->after('ai_image_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_clone_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ai_image_count', 'diff_content_per_page']);
        });
    }
};
