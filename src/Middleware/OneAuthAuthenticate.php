<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OneAuthAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app(\Libinkk\OneAuth\OneAuthManager::class)->driver()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
