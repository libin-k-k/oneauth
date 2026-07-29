<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Device extends Model
{
    protected $table = 'oneauth_devices';
    protected $guarded = [];
    protected $casts = ['trusted' => 'bool', 'first_login_at' => 'datetime', 'last_login_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
