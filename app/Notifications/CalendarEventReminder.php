<?php

namespace App\Notifications;

use App\Models\CalendarEvent;
use App\Notifications\Channels\SmsChannel;
use App\Support\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CalendarEventReminder extends Notification
{
    use Queueable;

    public function __construct(public CalendarEvent $event)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        $event = $this->event;

        $content = sprintf(
            'Reminder: %s — %s on %s%s.',
            $event->typeLabel(),
            $event->title,
            $event->all_day
                ? $event->starts_at?->format('d M Y')
                : $event->starts_at?->format('d M Y H:i'),
            $event->location ? ' at '.$event->location : ''
        );

        return SmsMessage::make($content);
    }
}
