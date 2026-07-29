<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'oneauth_login_attempts';
    protected $guarded = [];
    protected $casts = ['successful' => 'bool', 'attempted_at' => 'datetime'];
}
