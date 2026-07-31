<?php

namespace Libinkk\OneAuth\Events;

class AccountLocked
{
    public function __construct(public mixed $user, public array $context = [])
    {
    }
}
