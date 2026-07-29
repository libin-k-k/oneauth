<?php

namespace Libinkk\OneAuth\DTO;

class AuthResult
{
    public function __construct(
        public mixed $user,
        public ?string $token = null,
        public ?string $refreshToken = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'token' => $this->token,
            'refresh_token' => $this->refreshToken,
        ];
    }
}
