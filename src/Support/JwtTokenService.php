<?php

namespace Libinkk\OneAuth\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Models\RefreshToken;

class JwtTokenService
{
    public function issueAccessToken(mixed $user): string
    {
        $now = time();
        $ttl = (int) config('oneauth.jwt.ttl_minutes', 60) * 60;
        $secret = (string) config('oneauth.jwt.secret');

        return JWT::encode([
            'iss' => (string) config('oneauth.jwt.issuer', 'oneauth'),
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $secret, 'HS256');
    }

    public function issueRefreshToken(mixed $user): string
    {
        $token = Str::random(64);
        RefreshToken::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes((int) config('oneauth.jwt.refresh_ttl_minutes', 10080)),
        ]);

        return $token;
    }

    public function rotateRefreshToken(string $refreshToken): ?RefreshToken
    {
        $row = RefreshToken::query()
            ->where('token_hash', hash('sha256', $refreshToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return null;
        }

        $row->update(['revoked_at' => now()]);

        return $row;
    }

    public function decodeAccessToken(string $token): object
    {
        return JWT::decode($token, new Key((string) config('oneauth.jwt.secret'), 'HS256'));
    }
}
