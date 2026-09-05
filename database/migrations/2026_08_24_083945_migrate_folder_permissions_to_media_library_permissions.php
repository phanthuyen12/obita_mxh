<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->replacePermission('edit_content', 'edit_media');
            $this->replacePermission('delete_content', 'delete_media');
            $this->replacePermission('manage', 'manage_folder');
            DB::table('folder_permissions')->where('permission', 'create_post')->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $this->replacePermission('edit_media', 'edit_content');
            $this->replacePermission('delete_media', 'delete_content');
            $this->replacePermission('manage_folder', 'manage');
        });
    }

    private function replacePermission(string $from, string $to): void
    {
        DB::table('folder_permissions')
            ->where('permission', $from)
            ->orderBy('id')
            ->each(function (object $permission) use ($to): void {
                $duplicate = DB::table('folder_permissions')
                    ->where('folder_id', $permission->folder_id)
                    ->where('permission', $to)
                    ->where('user_id', $permission->user_id)
                    ->where('team_id', $permission->team_id)
                    ->exists();

                if ($duplicate) {
                    DB::table('folder_permissions')->where('id', $permission->id)->delete();

                    return;
                }

                DB::table('folder_permissions')->where('id', $permission->id)->update(['permission' => $to]);
            });
    }
};
