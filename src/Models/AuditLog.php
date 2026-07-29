<?php

namespace Libinkk\OneAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $table = 'oneauth_audit_logs';
    protected $guarded = [];
    protected $casts = ['context' => 'array', 'occurred_at' => 'datetime'];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
