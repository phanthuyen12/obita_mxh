<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveChatLoginController extends Controller
{
    /**
     * Dedicated mobile-style login for /omnichat/livechat.
     * Shares the same credentials and session as the main login.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect('/omnichat/livechat');
        }

        return Inertia::render('auth/LoginLiveChat', [
            'status' => session('status'),
            'redirect' => $request->query('redirect', '/omnichat/livechat'),
        ]);
    }
}
