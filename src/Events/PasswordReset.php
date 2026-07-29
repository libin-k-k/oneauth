<?php

namespace Libinkk\OneAuth\Events;

class PasswordReset
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

