<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Extends the framework Authenticate middleware so unauthenticated requests to
 * the mobile LiveChat area are sent to its dedicated login page instead of the
 * generic app login.
 */
class Authenticate extends Middleware
{
    /**
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        if ($request->expectsJson()) {
            return null;
        }

        return $request->is('omnichat/livechat*')
            ? route('login.livechat')
            : route('login');
    }
}
