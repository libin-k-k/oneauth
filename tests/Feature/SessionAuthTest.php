<?php

namespace Libinkk\OneAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Libinkk\OneAuth\Facades\OneAuth;
use Libinkk\OneAuth\Tests\TestCase;

class SessionAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_and_login_with_session_driver(): void
    {
        config(['oneauth.driver' => 'session']);

        $registered = OneAuth::register([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $this->assertArrayHasKey('user', $registered);

        $loggedIn = OneAuth::login([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $this->assertArrayHasKey('user', $loggedIn);
        $this->assertNull($loggedIn['token']);
    }
}
