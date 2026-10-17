<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('email_contact_list_id')->constrained('email_contact_lists')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('status')->default('active'); // active, unsubscribed, bounced, complained
            $table->timestamps();

            $table->unique(['email_contact_list_id', 'email'], 'email_contacts_list_email_unique');
            $table->index(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_contacts');
    }
};
