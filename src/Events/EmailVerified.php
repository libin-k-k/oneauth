<?php

namespace Libinkk\OneAuth\Events;

class EmailVerified
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

