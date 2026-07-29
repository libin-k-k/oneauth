<?php

namespace Libinkk\OneAuth\Facades;

use Illuminate\Support\Facades\Facade;

class OneAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Libinkk\OneAuth\OneAuthManager::class;
    }
}
