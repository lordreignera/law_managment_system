<?php

namespace App\Support;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageBroadcaster
{
    public static function dispatch(Message $message): void
    {
        try {
            MessageSent::dispatch($message->fresh(['sender', 'attachments']));
        } catch (Throwable $exception) {
            Log::warning('Message broadcast failed.', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
