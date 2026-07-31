<?php

namespace Libinkk\OneAuth\Contracts;

interface AuthenticationDriverInterface
{
    /**
     * Validate credentials and return the authenticatable user without establishing a session or tokens.
     */
    public function attempt(array $credentials): mixed;

    /**
     * Establish an authenticated session and/or issue tokens for an already validated user.
     */
    public function establish(mixed $user): array;

    public function login(array $credentials): array;

    public function logout(): void;

    public function refresh(): array;

    public function user(): mixed;

    public function check(): bool;

    public function guest(): bool;

    public function token(): ?string;
}
