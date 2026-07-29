<?php

namespace Libinkk\OneAuth\Events;

class TwoFactorDisabled
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

