<?php

use App\Models\SocialAccount;
use App\Services\Social\FacebookAnalytics;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$account = SocialAccount::where('platform', 'facebook')->first();
$post = $account->postPlatforms()->whereNotNull('platform_post_id')->first();
$analytics = app(FacebookAnalytics::class);
$res = $analytics->fetchPostMetrics($post);
print_r($res);
