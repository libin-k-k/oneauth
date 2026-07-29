<?php

namespace Libinkk\OneAuth\Events;

class SocialLogin
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

