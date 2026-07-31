<?php

namespace Libinkk\OneAuth\Providers\Notification;

use Illuminate\Support\Facades\Mail;
use Libinkk\OneAuth\Contracts\NotificationProviderInterface;

class EmailNotificationProvider implements NotificationProviderInterface
{
    public function send(string $channel, string $to, string $message, array $context = []): void
    {
        $subject = (string) ($context['subject'] ?? 'OneAuth notification');

        Mail::raw($message, function ($mail) use ($to, $subject): void {
            $mail->to($to)->subject($subject);
        });
    }
}
