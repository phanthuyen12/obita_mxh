<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailCampaignAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_unless($workspace !== null, Response::HTTP_FORBIDDEN);
        Gate::forUser($request->user())->authorize('manageEmailCampaign', $workspace);

        return $next($request);
    }
}
