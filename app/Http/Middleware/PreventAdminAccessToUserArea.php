<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAdminAccessToUserArea
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Prevent admin or staff roles from accessing this route group
        if ($user && ! $user->isStandardUser()) {
            abort(403, 'Admins and staff cannot access this section, using staff account');
        }

        return $next($request);
    }
}
