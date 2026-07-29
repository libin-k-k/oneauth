<?php

namespace Libinkk\OneAuth\DTO;

class AuthCredentials
{
    public function __construct(
        public string $identifier,
        public string $password
    ) {
    }

    public static function fromArray(array $data): self
    {
        $identifier = (string) ($data['email'] ?? $data['username'] ?? $data['phone'] ?? '');
        return new self($identifier, (string) ($data['password'] ?? ''));
    }
}
