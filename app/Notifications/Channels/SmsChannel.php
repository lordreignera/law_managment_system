<?php

namespace App\Notifications\Channels;

use App\Support\Sms\SmsGateway;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsResult;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    public function send(object $notifiable, Notification $notification): ?SmsResult
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (empty($to)) {
            return null;
        }

        $message = $notification->toSms($notifiable);
        $content = $message instanceof SmsMessage ? $message->content : (string) $message;

        if (trim($content) === '') {
            return null;
        }

        return $this->gateway->send(is_array($to) ? $to : [$to], $content);
    }
}
