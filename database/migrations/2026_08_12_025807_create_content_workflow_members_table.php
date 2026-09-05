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
        Schema::create('content_workflow_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_write')->default(false);
            $table->boolean('can_review')->default(false);
            $table->boolean('can_publish')->default(false);
            $table->timestamps();

            $table->unique(['content_workflow_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_workflow_members');
    }
};
