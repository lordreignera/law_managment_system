<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Notification;

class MessageReceived extends Notification
{
    public function __construct(private readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing(['sender', 'conversation']);
        $isClient = $notifiable->isClientAccount();

        return [
            'event_key' => 'message.received',
            'subject' => 'New message from '.($this->message->sender?->name ?: 'User'),
            'body' => str($this->message->body)->limit(160)->toString(),
            'url' => $isClient
                ? route('client.messages.show', $this->message->conversation)
                : route('messages.show', $this->message->conversation),
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
        ];
    }
}
