<?php

namespace Libinkk\OneAuth\Events;

class SuspiciousLoginDetected
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}
