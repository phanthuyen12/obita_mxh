<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_campaign_id')->nullable()->constrained('email_campaigns')->nullOnDelete();
            $table->string('email');
            $table->string('reason')->default('unsubscribed'); // unsubscribed, bounced, complained, manual
            $table->timestamps();

            $table->unique(['workspace_id', 'email'], 'email_suppressions_workspace_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
