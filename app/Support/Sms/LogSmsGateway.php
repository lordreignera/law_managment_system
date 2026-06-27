<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(array $recipients, string $message): SmsResult
    {
        $recipients = array_values(array_filter(array_map('trim', $recipients)));

        Log::channel(config('sms.log_channel') ?: config('logging.default'))
            ->info('SMS dispatched via log gateway.', [
                'to' => $recipients,
                'message' => $message,
            ]);

        return SmsResult::success($recipients, ['driver' => 'log']);
    }
}
