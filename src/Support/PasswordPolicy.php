<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Validation\ValidationException;

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
    }
}
