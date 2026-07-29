<?php

if (!function_exists('oneauth_user_model')) {
    function oneauth_user_model(): string
    {
        return (string) config('oneauth.user_model', \App\Models\User::class);
    }
}
