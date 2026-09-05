<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_account_accesses')) {
            Schema::table('social_account_accesses', function (Blueprint $table): void {
                if (! Schema::hasColumn('social_account_accesses', 'can_access_content')) {
                    $table->boolean('can_access_content')->default(true)->after('can_assign_conversations');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('social_account_accesses', function (Blueprint $table): void {
            $table->dropColumn('can_access_content');
        });
    }
};
