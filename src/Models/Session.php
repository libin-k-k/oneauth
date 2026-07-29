<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Session extends Model
{
    protected $table = 'oneauth_sessions';
    protected $guarded = [];
    protected $casts = ['last_activity_at' => 'datetime', 'expires_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
