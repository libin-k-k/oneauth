<?php

namespace Libinkk\OneAuth\Contracts;

interface OTPProviderInterface
{
    public function send(string $channel, string $to, string $code, array $context = []): void;
}
