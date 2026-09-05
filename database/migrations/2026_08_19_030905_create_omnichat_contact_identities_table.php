<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omnichat_contact_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('omnichat_contacts')->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('external_id');
            $table->string('display_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['workspace_id', 'provider', 'external_id'], 'omnichat_identities_ws_prov_ext_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichat_contact_identities');
    }
};
