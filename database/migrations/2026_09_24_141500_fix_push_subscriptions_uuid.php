<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->char('subscribable_id', 36)->change();
        });
    }

    public function down(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('subscribable_id')->change();
        });
    }
};
