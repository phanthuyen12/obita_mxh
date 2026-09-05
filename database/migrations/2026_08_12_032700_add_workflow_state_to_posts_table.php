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
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('workflow_status', 30)->default('draft')->after('content_workflow_id');
            $table->text('workflow_note')->nullable()->after('workflow_status');
            $table->index(['content_workflow_id', 'workflow_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['content_workflow_id', 'workflow_status']);
            $table->dropColumn(['workflow_status', 'workflow_note']);
        });
    }
};
