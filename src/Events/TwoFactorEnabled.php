<?php

namespace Libinkk\OneAuth\Events;

class TwoFactorEnabled
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}

