<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RefreshToken extends Model
{
    protected $table = 'oneauth_refresh_tokens';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
