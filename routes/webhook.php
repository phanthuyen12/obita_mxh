<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\FacebookMessengerMediaController;
use App\Http\Controllers\Webhooks\FacebookMessengerWebhookController;
use App\Http\Controllers\Webhooks\LazadaWebhookController;
use App\Http\Controllers\Webhooks\ShopeeWebhookController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\ZaloOaMediaController;
use App\Http\Controllers\Webhooks\ZaloOaWebhookController;
use Illuminate\Support\Facades\Route;

Route::group([
    'domain' => parse_url(config('app.webhook_url'), PHP_URL_HOST) ?: config('app.webhook_url'),
], function () {
    Route::get('facebook/messenger/webhook', [FacebookMessengerWebhookController::class, 'verify'])
        ->name('facebook.messenger.webhook.verify');
    Route::post('facebook/messenger/webhook', [FacebookMessengerWebhookController::class, 'handle'])
        ->name('facebook.messenger.webhook');

    Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
});

Route::post('webhooks/lazada', LazadaWebhookController::class)->name('lazada.webhook');
Route::post('webhooks/shopee', ShopeeWebhookController::class)->name('shopee.webhook');
Route::post('webhooks/zalo-oa', ZaloOaWebhookController::class)->name('zalo-oa.webhook');
Route::get('facebook-messenger-media/{path}', FacebookMessengerMediaController::class)
    ->where('path', '.*')
    ->name('facebook.messenger.media');
Route::get('zalo-media/{path}', ZaloOaMediaController::class)
    ->where('path', '.*')
    ->name('zalo.media');
