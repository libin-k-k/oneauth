<?php

namespace Libinkk\OneAuth\Events;

class PasswordChanged
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

