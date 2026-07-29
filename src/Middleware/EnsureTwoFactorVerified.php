<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('oneauth.twofactor_verified', false)) {
            return response()->json(['message' => 'Two-factor verification is required.'], 403);
        }

        return $next($request);
    }
}
