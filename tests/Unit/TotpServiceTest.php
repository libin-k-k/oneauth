<?php

namespace Libinkk\OneAuth\Tests\Unit;

use Libinkk\OneAuth\Support\TotpService;
use Libinkk\OneAuth\Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generate_and_verify_totp_code(): void
    {
        $service = new TotpService();
        $secret = $service->generateSecret();
        $code = $service->nowCode($secret);

        $this->assertTrue($service->verify($secret, $code));
    }
}
