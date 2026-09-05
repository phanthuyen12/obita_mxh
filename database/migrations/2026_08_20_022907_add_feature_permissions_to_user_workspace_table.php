<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->boolean('can_omnichat')->default(true)->after('role');
            $table->boolean('can_content')->default(true)->after('can_omnichat');
        });
    }

    public function down(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropColumn(['can_omnichat', 'can_content']);
        });
    }
};
