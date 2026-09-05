<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_account_accesses', function (Blueprint $table): void {
            $table->boolean('can_view_omnichat')->default(true);
            $table->boolean('can_reply_omnichat')->default(true);
            $table->boolean('can_assign_conversations')->default(false);
            $table->boolean('can_create_posts')->default(true);
            $table->boolean('can_edit_posts')->default(true);
            $table->boolean('can_approve_posts')->default(false);
            $table->boolean('can_publish_posts')->default(true);
            $table->boolean('can_delete_posts')->default(false);
        });

        Schema::table('omnichat_conversations', function (Blueprint $table): void {
            $table->foreignUuid('assigned_user_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->index(['workspace_id', 'assigned_user_id', 'status', 'last_message_at'], 'omnichat_conv_ws_assign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('omnichat_conversations', function (Blueprint $table): void {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex('omnichat_conv_ws_assign_status_idx');
            $table->dropColumn('assigned_user_id');
        });

        Schema::table('social_account_accesses', function (Blueprint $table): void {
            $table->dropColumn([
                'can_view_omnichat', 'can_reply_omnichat', 'can_assign_conversations',
                'can_create_posts', 'can_edit_posts', 'can_approve_posts',
                'can_publish_posts', 'can_delete_posts',
            ]);
        });
    }
};
