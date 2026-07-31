<?php

namespace Libinkk\OneAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Libinkk\OneAuth\OneAuthManager;

class OneAuthController
{
    public function __construct(private OneAuthManager $auth)
    {
    }

    public function register(Request $request): JsonResponse
    {
        return response()->json($this->auth->register($request->all()), 201);
    }

    public function login(Request $request): JsonResponse
    {
        return response()->json($this->auth->login($request->all()));
    }

    public function anonymousLogin(Request $request): JsonResponse
    {
        return response()->json($this->auth->anonymousLogin($request->all()), 201);
    }

    public function loginWithOtp(Request $request): JsonResponse
    {
        return response()->json($this->auth->loginWithOtp($request->all()));
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();
        return response()->json(['message' => 'Logged out.']);
    }

    public function user(): JsonResponse
    {
        return response()->json(['user' => $this->auth->user()]);
    }

    public function refresh(Request $request): JsonResponse
    {
        return response()->json($this->auth->refresh());
    }

    public function sendOtp(Request $request): JsonResponse
    {
        return response()->json($this->auth->sendOtp($request->all()));
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        return response()->json(['verified' => $this->auth->verifyOtp($request->all())]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        return response()->json(['verified' => $this->auth->verifyEmail($request->all())]);
    }

    public function verifySignedEmail(Request $request): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['verified' => false, 'message' => 'Invalid or expired signature.'], 422);
        }

        return response()->json(['verified' => $this->auth->verifyEmail($request->all())]);
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        return response()->json($this->auth->sendEmailVerification($request->all()));
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        return response()->json($this->auth->enableTwoFactor($request->all()));
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        return response()->json(['disabled' => $this->auth->disableTwoFactor($request->all())]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        return response()->json(['verified' => $this->auth->verifyTwoFactor($request->all())]);
    }

    public function completeTwoFactorLogin(Request $request): JsonResponse
    {
        return response()->json($this->auth->completeTwoFactorLogin($request->all()));
    }

    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        return response()->json($this->auth->socialLogin($provider, $request->all()));
    }

    public function sessions(): JsonResponse
    {
        return response()->json(['sessions' => $this->auth->sessions()]);
    }

    public function revokeSession(string $sessionId): JsonResponse
    {
        return response()->json([
            'revoked' => $this->auth->revokeSession($sessionId),
        ]);
    }

    public function logoutOtherSessions(): JsonResponse
    {
        return response()->json([
            'revoked' => $this->auth->logoutOtherSessions(),
        ]);
    }

    public function devices(): JsonResponse
    {
        return response()->json(['devices' => $this->auth->devices()]);
    }

    public function trustDevice(Request $request, string $fingerprint): JsonResponse
    {
        $trusted = filter_var($request->input('trusted', true), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'trusted' => $this->auth->trustDevice($fingerprint, $trusted),
        ]);
    }
}
