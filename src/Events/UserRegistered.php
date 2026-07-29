<?php

namespace Libinkk\OneAuth\Events;

class UserRegistered
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

