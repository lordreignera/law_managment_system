<?php

namespace App\Support\Sms;

class SmsMessage
{
    public function __construct(public string $content = '')
    {
    }

    public static function make(string $content = ''): self
    {
        return new self($content);
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
