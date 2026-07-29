<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TwoFactor extends Model
{
    protected $table = 'oneauth_two_factor';
    protected $guarded = [];
    protected $casts = ['enabled' => 'bool', 'recovery_codes' => 'array', 'enabled_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
