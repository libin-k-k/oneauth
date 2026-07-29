<?php

namespace Libinkk\OneAuth\Contracts;

interface OAuthProviderInterface
{
    public function provider(): string;

    public function userFromToken(string $token): array;
}
