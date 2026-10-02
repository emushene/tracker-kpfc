<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKpfcAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.kpfc_admin.integration_token');

        if (! $expectedToken) {
            return response()->json([
                'message' => 'Integration authentication is not configured.',
            ], 503);
        }

        $providedToken = $request->bearerToken();

        if (! $providedToken || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}

