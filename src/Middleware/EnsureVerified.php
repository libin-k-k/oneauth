<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = app(\Libinkk\OneAuth\OneAuthManager::class)->driver()->user();
        if (!$user || (isset($user->email_verified_at) && !$user->email_verified_at)) {
            return response()->json(['message' => 'Email is not verified.'], 403);
        }

        return $next($request);
    }
}
