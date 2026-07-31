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

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertTrue($service->verify($secret, $code));
    }

    public function test_otpauth_uri_includes_issuer_and_secret(): void
    {
        config(['oneauth.two_factor.totp_issuer' => 'OneAuthTest']);
        $service = new TotpService();
        $secret = $service->generateSecret();
        $uri = $service->otpauthUri($secret, 'user@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=OneAuthTest', $uri);
    }
}
