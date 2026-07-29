<?php

namespace Libinkk\OneAuth\Events;

class UserLoggedIn
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

