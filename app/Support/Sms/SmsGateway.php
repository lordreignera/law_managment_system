<?php

namespace App\Support\Sms;

interface SmsGateway
{
    /**
     * Send an SMS to one or more recipients.
     *
     * @param  array<int, string>  $recipients
     */
    public function send(array $recipients, string $message): SmsResult;
}
