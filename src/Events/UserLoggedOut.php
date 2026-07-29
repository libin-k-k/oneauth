<?php

namespace Libinkk\OneAuth\Events;

class UserLoggedOut
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

