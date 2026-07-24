<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockImpersonatedAccess
{
    /**
     * Class BlockImpersonatedAccess
     *
     * Middleware to prevent access to certain routes while impersonating another user.
     * Typically applied to sensitive areas like account settings or admin-only routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the current user is impersonating someone else
        if (auth()->check() && ImpersonationSession::isImpersonating()) {
            // Block access with 403 or redirect
            abort(403, 'You cannot access this section while impersonating.');
        }

        return $next($request);
    }
}
