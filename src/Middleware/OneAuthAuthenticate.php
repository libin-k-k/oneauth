<?php

namespace Libinkk\OneAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Libinkk\OneAuth\Drivers\JwtDriver;
use Libinkk\OneAuth\OneAuthManager;
use Symfony\Component\HttpFoundation\Response;

class OneAuthAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = app(OneAuthManager::class);
        $driver = $manager->driver();

        if (!$driver->check()) {
            $this->authenticateBearer($request, $manager);
        }

        if (!$manager->driver()->check() && !Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }

    protected function authenticateBearer(Request $request, OneAuthManager $manager): void
    {
        $header = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return;
        }

        $token = $matches[1];
        $driver = $manager->driver();

        if ($driver instanceof JwtDriver) {
            $driver->authenticateBearer($token);

            return;
        }

        // When configured for another driver, still accept a valid OneAuth JWT if present.
        $jwt = app(JwtDriver::class);
        $jwt->authenticateBearer($token);
    }
}
