<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omnichat_conversation_tag', function (Blueprint $table): void {
            $table->foreignUuid('omnichat_conversation_id')->constrained('omnichat_conversations')->cascadeOnDelete();
            $table->foreignUuid('omnichat_tag_id')->constrained('omnichat_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['omnichat_conversation_id', 'omnichat_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnichat_conversation_tag');
    }
};
