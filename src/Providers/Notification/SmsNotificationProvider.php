<?php

namespace Libinkk\OneAuth\Providers\Notification;

use Libinkk\OneAuth\Contracts\NotificationProviderInterface;
use Libinkk\OneAuth\Exceptions\OneAuthException;

class SmsNotificationProvider implements NotificationProviderInterface
{
    public function send(string $channel, string $to, string $message, array $context = []): void
    {
        throw new OneAuthException(
            'SMS notification provider is a contract stub. Bind your own NotificationProviderInterface implementation.'
        );
    }
}
