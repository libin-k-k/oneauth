<?php

namespace Libinkk\OneAuth\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Libinkk\OneAuth\Models\Device;
use Libinkk\OneAuth\Models\Session;
use Libinkk\OneAuth\Models\SocialAccount;

trait HasOneAuth
{
    public function oneauthSessions(): MorphMany
    {
        return $this->morphMany(Session::class, 'authenticatable');
    }

    public function oneauthDevices(): MorphMany
    {
        return $this->morphMany(Device::class, 'authenticatable');
    }

    public function oneauthSocialAccounts(): MorphMany
    {
        return $this->morphMany(SocialAccount::class, 'authenticatable');
    }
}
