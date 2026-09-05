<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->foreignUuid('post_id')
                ->constrained('posts')
                ->cascadeOnDelete();

            $table->foreignUuid('media_id')
                ->constrained('medias')
                ->cascadeOnDelete();

            $table->integer('sort_order')->default(0);

            $table->string('alt_text')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique([
                'post_id',
                'media_id',
            ]);

            $table->index('media_id');

            $table->index([
                'post_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
