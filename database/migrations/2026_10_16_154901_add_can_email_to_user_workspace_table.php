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
                if (! Schema::hasColumn('user_workspace', 'can_email')) {
                    $table->boolean('can_email')->default(false)->after('can_content');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropColumn('can_email');
        });
    }
};
