<?php

namespace App\Support;

use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Notifications\MessageReceived;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Notification;

class MessageNotifier
{
    public static function notifyParticipants(Message $message): void
    {
        $message->loadMissing(['sender', 'conversation']);

        $recipientIds = ConversationParticipant::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = \App\Models\User::whereIn('id', $recipientIds)->get();
        try {
            Notification::send($recipients, new MessageReceived($message));

            $push = app(PushNotificationService::class);
            foreach ($recipients as $recipient) {
                $push->sendTo($recipient, [
                    'title' => 'New KFMS message',
                    'body' => 'You have a new message waiting in KFMS.',
                    'url' => $recipient->isClientAccount()
                        ? route('client.messages.show', $message->conversation)
                        : route('messages.show', $message->conversation),
                    'tag' => 'kfms-message-'.$message->conversation_id,
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
