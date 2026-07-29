<?php

namespace Libinkk\OneAuth\Contracts;

interface AuthenticationDriverInterface
{
    public function login(array $credentials): array;

    public function logout(): void;

    public function refresh(): array;

    public function user(): mixed;

    public function check(): bool;

    public function guest(): bool;

    public function token(): ?string;
}
