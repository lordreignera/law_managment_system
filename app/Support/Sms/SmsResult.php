<?php

namespace App\Support\Sms;

final class SmsResult
{
    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $recipients = [],
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $raw
     */
    public static function success(array $recipients, array $raw = []): self
    {
        return new self(true, $recipients, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $error, array $raw = []): self
    {
        return new self(false, [], $error, $raw);
    }
}
