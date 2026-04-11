<?php

namespace App\Http\Middleware;

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
     *
     * @package App\Http\Middleware
     */
    
    public function handle(Request $request, Closure $next): Response
    {
        // If the current user is impersonating someone else
        if (auth()->check() && session()->has('impersonator_id')) {
            // Block access with 403 or redirect
            abort(403, 'You cannot access this section while impersonating.');
        }

        return $next($request);
    }
}
