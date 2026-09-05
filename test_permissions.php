<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$folder = App\Models\Folder::first();
if (!$folder) {
    echo "No folder.\n";
    exit;
}

$permissions = $folder->permissions()->with(['user:id,name,email', 'team:id,name'])->get();
echo json_encode($permissions->toArray(), JSON_PRETTY_PRINT);
