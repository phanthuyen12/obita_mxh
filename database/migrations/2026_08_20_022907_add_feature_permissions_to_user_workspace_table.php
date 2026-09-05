<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_workspace')) {
            Schema::table('user_workspace', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_workspace', 'can_omnichat')) {
                    $table->boolean('can_omnichat')->default(true)->after('role');
                }
                if (! Schema::hasColumn('user_workspace', 'can_content')) {
                    $table->boolean('can_content')->default(true)->after('can_omnichat');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropColumn(['can_omnichat', 'can_content']);
        });
    }
};
