<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $verified = session('oneauth.otp_verified');

        if ($verified === null || $verified === false || $verified === '') {
            return response()->json(['message' => 'OTP verification is required.'], 403);
        }

        if ($purpose !== null && $purpose !== '' && $verified !== true && $verified !== $purpose) {
            return response()->json(['message' => 'OTP verification is required for this purpose.'], 403);
        }

        return $next($request);
    }
}
