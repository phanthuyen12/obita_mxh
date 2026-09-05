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
        if (Schema::hasTable('social_account_user')) {
            return;
        }

        if (Schema::hasTable('omnichat_social_account_user')) {
            Schema::rename('omnichat_social_account_user', 'social_account_user');

            return;
        }

        Schema::create('social_account_user', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'social_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** This repair migration must not delete a table that may predate it. */
    }
};
