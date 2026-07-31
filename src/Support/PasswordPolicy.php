<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Libinkk\OneAuth\Models\PasswordHistory;

class PasswordPolicy
{
    public function validate(string $password): void
    {
        $rules = (array) config('oneauth.password_policy', []);
        $min = (int) ($rules['min_length'] ?? 8);

        if (mb_strlen($password) < $min) {
            throw ValidationException::withMessages(['password' => ['Password must be at least ' . $min . ' characters.']]);
        }

        if (($rules['require_uppercase'] ?? true) && !preg_match('/[A-Z]/', $password)) {
            throw ValidationException::withMessages(['password' => ['Password must include an uppercase letter.']]);
        }

        if (($rules['require_lowercase'] ?? true) && !preg_match('/[a-z]/', $password)) {
            throw ValidationException::withMessages(['password' => ['Password must include a lowercase letter.']]);
        }

        if (($rules['require_number'] ?? true) && !preg_match('/\d/', $password)) {
            throw ValidationException::withMessages(['password' => ['Password must include a number.']]);
        }

        if (($rules['require_symbol'] ?? false) && !preg_match('/[\W_]/', $password)) {
            throw ValidationException::withMessages(['password' => ['Password must include a symbol.']]);
        }
    }

    public function assertNotReused(mixed $user, string $plainPassword): void
    {
        $limit = (int) config('oneauth.password_policy.history_limit', 5);
        if ($limit <= 0) {
            return;
        }

        if (isset($user->password) && Hash::check($plainPassword, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => ['Password must not match a recently used password.']]);
        }

        $histories = PasswordHistory::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($histories as $history) {
            if (Hash::check($plainPassword, (string) $history->password_hash)) {
                throw ValidationException::withMessages(['password' => ['Password must not match a recently used password.']]);
            }
        }
    }

    public function recordHistory(mixed $user): void
    {
        PasswordHistory::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'password_hash' => (string) $user->password,
            'changed_at' => now(),
        ]);

        $limit = (int) config('oneauth.password_policy.history_limit', 5);
        if ($limit <= 0) {
            return;
        }

        $keepIds = PasswordHistory::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->latest('id')
            ->limit($limit)
            ->pluck('id');

        PasswordHistory::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
