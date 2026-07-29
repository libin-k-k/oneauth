<?php

namespace Libinkk\OneAuth\Events;

class OTPSent
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

