<?php

declare(strict_types=1);

use App\Http\Controllers\EmailUnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public email routes
|--------------------------------------------------------------------------
|
| Reached from campaign emails, so these must work without a session: the
| recipient is identified by the per-recipient unsubscribe token.
|
*/

Route::get('email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'show'])
    ->name('email.unsubscribe');

Route::post('email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'store'])
    ->withoutMiddleware(['web'])
    ->name('email.unsubscribe.store');
