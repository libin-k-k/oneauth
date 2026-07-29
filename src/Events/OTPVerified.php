<?php

namespace Libinkk\OneAuth\Events;

class OTPVerified
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

