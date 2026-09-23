<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanWrite
{
    /**
     * Handle an incoming request.
     *
     * Ensures that mutating/write requests (POST, PUT, PATCH, DELETE) are only
     * executed by authenticated users possessing authorized write roles.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read methods are safe and allowed for all authenticated fleet users
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->canWrite()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to modify fleet data.',
                ], 403);
            }

            abort(403, 'You do not have permission to modify fleet data.');
        }

        return $next($request);
    }
}
