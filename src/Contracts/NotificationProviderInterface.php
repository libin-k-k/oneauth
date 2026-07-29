<?php

namespace Libinkk\OneAuth\Contracts;

interface NotificationProviderInterface
{
    public function send(string $channel, string $to, string $message, array $context = []): void;
}
