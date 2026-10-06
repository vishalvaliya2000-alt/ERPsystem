<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! empty($roles) && ! $user->hasRole($roles)) {
            abort(403, 'Unauthorized. Your role ('.$user->role.') does not have permission to access this section.');
        }

        return $next($request);
    }
}
