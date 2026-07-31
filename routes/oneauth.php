<?php

use Illuminate\Support\Facades\Route;
use Libinkk\OneAuth\Http\Controllers\OneAuthController;

Route::group([
    'prefix' => config('oneauth.routes.prefix', 'oneauth'),
    'middleware' => config('oneauth.routes.middleware', ['api']),
], function (): void {
    Route::post('/register', [OneAuthController::class, 'register']);
    Route::post('/login', [OneAuthController::class, 'login']);
    Route::post('/login/anonymous', [OneAuthController::class, 'anonymousLogin']);
    Route::post('/login/otp', [OneAuthController::class, 'loginWithOtp']);
    Route::post('/refresh', [OneAuthController::class, 'refresh']);
    Route::post('/social/{provider}/login', [OneAuthController::class, 'socialLogin']);
    Route::post('/2fa/challenge', [OneAuthController::class, 'completeTwoFactorLogin']);
    Route::get('/email/verify/signed', [OneAuthController::class, 'verifySignedEmail'])->name('oneauth.email.verify.signed');
    Route::post('/password/forgot', [\Libinkk\OneAuth\Http\Controllers\PasswordController::class, 'forgot']);
    Route::post('/password/reset', [\Libinkk\OneAuth\Http\Controllers\PasswordController::class, 'reset']);
    Route::post('/otp/send', [OneAuthController::class, 'sendOtp']);
    Route::post('/otp/verify', [OneAuthController::class, 'verifyOtp']);

    Route::middleware('oneauth.auth')->group(function (): void {
        Route::post('/logout', [OneAuthController::class, 'logout']);
        Route::get('/user', [OneAuthController::class, 'user']);
        Route::post('/email/verify', [OneAuthController::class, 'verifyEmail']);
        Route::post('/email/send-verification', [OneAuthController::class, 'sendEmailVerification']);
        Route::post('/2fa/enable', [OneAuthController::class, 'enableTwoFactor']);
        Route::post('/2fa/verify', [OneAuthController::class, 'verifyTwoFactor']);
        Route::post('/2fa/disable', [OneAuthController::class, 'disableTwoFactor']);
        Route::get('/sessions', [OneAuthController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [OneAuthController::class, 'revokeSession']);
        Route::post('/sessions/logout-others', [OneAuthController::class, 'logoutOtherSessions']);
        Route::get('/devices', [OneAuthController::class, 'devices']);
        Route::post('/devices/{fingerprint}/trust', [OneAuthController::class, 'trustDevice']);
        Route::post('/password/change', [\Libinkk\OneAuth\Http\Controllers\PasswordController::class, 'change']);
    });
});
