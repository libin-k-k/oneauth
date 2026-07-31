<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;

class AccountLock extends Model
{
    protected $table = 'oneauth_account_locks';
    protected $guarded = [];
    protected $casts = [
        'locked_until' => 'datetime',
        'meta' => 'array',
    ];
}
