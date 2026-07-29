<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailVerification extends Model
{
    protected $table = 'oneauth_email_verifications';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
