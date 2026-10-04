<?php

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
        if (! Schema::hasTable('ai_sale_bot_knowledges')) {
            Schema::create('ai_sale_bot_knowledges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('ai_bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
                $table->string('title');
                $table->string('file_type')->default('document'); // pdf, docx, txt, manual_text
                $table->string('file_path')->nullable();
                $table->longText('content')->nullable();
                $table->integer('token_count')->default(0);
                $table->string('status')->default('ready'); // processing, ready, failed
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
            });
        }

        if (! Schema::hasTable('ai_sale_bot_products')) {
            Schema::create('ai_sale_bot_products', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('sku')->index();
                $table->string('name')->index();
                $table->string('unit')->default('cái');
                $table->decimal('price', 14, 2)->default(0);
                $table->decimal('sale_price', 14, 2)->nullable();
                $table->integer('stock_quantity')->default(0);
                $table->text('description')->nullable();
                $table->text('image_url')->nullable();
                $table->json('attributes')->nullable(); // Size, màu sắc, combo
                $table->boolean('is_in_stock')->default(true)->index();
                $table->timestamps();

                $table->index(['workspace_id', 'is_in_stock']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_sale_bot_products');
        Schema::dropIfExists('ai_sale_bot_knowledges');
    }
};
