<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medias', function (Blueprint $table) {

            $table->foreignUuid('workspace_id')
                ->nullable()
                ->after('id')
                ->constrained('workspaces')
                ->nullOnDelete();

            $table->foreignUuid('folder_id')
                ->nullable()
                ->after('workspace_id')
                ->constrained('folders')
                ->nullOnDelete();

            $table->foreignUuid('uploaded_by')
                ->nullable()
                ->after('folder_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('disk')
                ->default('public');

            $table->text('original_path')
                ->nullable();

            $table->text('optimized_path')
                ->nullable();

            $table->text('thumbnail_path')
                ->nullable();

            $table->string('checksum', 64)
                ->nullable();

            $table->index([
                'workspace_id',
                'folder_id',
            ]);

            $table->index('uploaded_by');

            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table) {

            $table->dropForeign([
                'workspace_id',
            ]);

            $table->dropForeign([
                'folder_id',
            ]);

            $table->dropForeign([
                'uploaded_by',
            ]);

            $table->dropIndex(['workspace_id', 'folder_id']);
            $table->dropIndex(['uploaded_by']);
            $table->dropIndex(['checksum']);

            $table->dropColumn([
                'workspace_id',
                'folder_id',
                'uploaded_by',
                'disk',
                'original_path',
                'optimized_path',
                'thumbnail_path',
                'checksum',
            ]);
        });
    }
};
