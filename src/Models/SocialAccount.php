<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialAccount extends Model
{
    protected $table = 'oneauth_social_accounts';
    protected $guarded = [];
    protected $casts = ['meta' => 'array'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
