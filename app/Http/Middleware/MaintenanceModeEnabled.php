<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceModeEnabled = Setting::getBooleanValue('features.maintenance_mode', false);
        $user = auth()->user();
        $impersonator = session()->get('impersonator_id')
            ? User::find(session('impersonator_id'))
            : $user;

        if (! $maintenanceModeEnabled) {
            return $next($request);
        }

        if ($impersonator && $impersonator->can('byPassMaintenanceRole', User::class)) {
            return $next($request);
        }

        return response()->view('app', [
            'page' => [
                'component' => 'auth/maintenance',
                'props' => [],
                'url' => $request->getRequestUri(),
                'version' => null,
            ],
        ], 503);
    }
}
