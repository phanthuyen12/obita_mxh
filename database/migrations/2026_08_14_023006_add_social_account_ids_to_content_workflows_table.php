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
        Schema::table('content_workflows', function (Blueprint $table) {
            $table->json('social_account_ids')->nullable()->after('social_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_workflows', function (Blueprint $table) {
            $table->dropColumn('social_account_ids');
        });
    }
};
