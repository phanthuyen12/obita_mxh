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
        if (! Schema::hasTable('social_account_groups')) {
            Schema::create('social_account_groups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->timestamps();

                $table->unique(['workspace_id', 'name']);
            });
        }

        if (! Schema::hasTable('social_account_group_members')) {
            Schema::create('social_account_group_members', function (Blueprint $table) {
                $table->foreignUuid('social_account_group_id')->constrained('social_account_groups')->cascadeOnDelete();
                $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['social_account_group_id', 'social_account_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_account_group_members');
        Schema::dropIfExists('social_account_groups');
    }
};
