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
        if (Schema::hasTable('ai_bots')) {
            Schema::table('ai_bots', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_bots', 'dify_dataset_id')) {
                    $table->string('dify_dataset_id')->nullable()->after('dify_base_url');
                }
                if (! Schema::hasColumn('ai_bots', 'dify_dataset_api_key')) {
                    $table->text('dify_dataset_api_key')->nullable()->after('dify_dataset_id');
                }
            });
        }

        if (Schema::hasTable('ai_sale_bot_knowledges')) {
            Schema::table('ai_sale_bot_knowledges', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_sale_bot_knowledges', 'dify_document_id')) {
                    $table->string('dify_document_id')->nullable()->after('file_path');
                }
                if (! Schema::hasColumn('ai_sale_bot_knowledges', 'dify_batch_id')) {
                    $table->string('dify_batch_id')->nullable()->after('dify_document_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ai_bots')) {
            Schema::table('ai_bots', function (Blueprint $table): void {
                $table->dropColumn(['dify_dataset_id', 'dify_dataset_api_key']);
            });
        }

        if (Schema::hasTable('ai_sale_bot_knowledges')) {
            Schema::table('ai_sale_bot_knowledges', function (Blueprint $table): void {
                $table->dropColumn(['dify_document_id', 'dify_batch_id']);
            });
        }
    }
};
