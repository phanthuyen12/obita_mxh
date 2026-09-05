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
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->index(
                ['workspace_id', 'connected_by_user_id', 'id'],
                'social_accounts_workspace_owner_browser_index',
            );
        });

        Schema::table('social_account_group_members', function (Blueprint $table): void {
            $table->index(
                ['social_account_id', 'social_account_group_id'],
                'social_account_group_members_account_group_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_account_group_members', function (Blueprint $table): void {
            $table->dropIndex('social_account_group_members_account_group_index');
        });

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropIndex('social_accounts_workspace_owner_browser_index');
        });
    }
};
