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
        Schema::create('folder_permissions', function (Blueprint $table) {

            $table->uuid('id')->primary();

            // Folder được cấp quyền
            $table->foreignUuid('folder_id')
                ->constrained('folders')
                ->cascadeOnDelete();

            // Cấp quyền trực tiếp cho User
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            // Hoặc cấp quyền cho Team
            $table->foreignUuid('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnDelete();

            // Quyền
            $table->string('permission');

            // Ai là người cấp quyền
            $table->foreignUuid('assigned_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['folder_id', 'user_id', 'permission']);
            $table->unique(['folder_id', 'team_id', 'permission']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folder_permissions');
    }
};
