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
        Schema::create('omnichat_channel_accesses', function (Blueprint $table): void {
            $table->foreignUuid('omnichat_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('can_view_omnichat')->default(true);
            $table->boolean('can_reply_omnichat')->default(true);
            $table->boolean('can_assign_conversations')->default(false);
            $table->timestamps();

            $table->primary(['omnichat_channel_id', 'user_id']);
            $table->index(['user_id', 'omnichat_channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('omnichat_channel_accesses');
    }
};
