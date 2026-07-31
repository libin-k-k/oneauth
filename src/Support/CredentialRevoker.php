<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;

class CredentialRevoker
{
    public function revokeAll(mixed $user): void
    {
        app(SessionRepositoryInterface::class)->revokeAll($user);

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        app(JwtTokenService::class)->revokeAllForUser($user);
        CredentialVersion::bump($user);
        $this->invalidateLaravelSessions($user);
    }

    protected function invalidateLaravelSessions(mixed $user): void
    {
        if (request()->hasSession()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        } else {
            Auth::logout();
        }

        try {
            $table = (string) config('session.table', 'sessions');
            if (config('session.driver') === 'database' && Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $user->getAuthIdentifier())->delete();
            }
        } catch (\Throwable) {
            // Session driver may not support bulk invalidation.
        }
    }
}
