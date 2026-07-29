<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('oneauth.otp_verified', false)) {
            return response()->json(['message' => 'OTP verification is required.'], 403);
        }

        return $next($request);
    }
}
