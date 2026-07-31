<?php

namespace Libinkk\OneAuth\Tests\Unit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Libinkk\OneAuth\Drivers\SanctumDriver;
use Libinkk\OneAuth\Tests\TestCase;

class SanctumDriverRefreshTest extends TestCase
{
    public function test_refresh_deletes_current_access_token_before_issuing_new_one(): void
    {
        $deleted = false;
        $issued = 0;

        $user = new class($deleted, $issued) implements Authenticatable {
            public function __construct(private bool &$deleted, private int &$issued)
            {
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return 'hash';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName()
            {
                return 'remember_token';
            }

            public function currentAccessToken()
            {
                return new class($this->deleted) {
                    public function __construct(private bool &$deleted)
                    {
                    }

                    public function delete(): void
                    {
                        $this->deleted = true;
                    }
                };
            }

            public function createToken(string $name): object
            {
                $this->issued++;

                return new class {
                    public string $plainTextToken = 'fresh-token';
                };
            }
        };

        Auth::setUser($user);

        $result = app(SanctumDriver::class)->refresh();

        $this->assertTrue($deleted);
        $this->assertSame(1, $issued);
        $this->assertSame('fresh-token', $result['token']);
    }
}
