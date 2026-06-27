<?php

namespace App\Notifications;

use App\Models\CourtEvent;
use App\Notifications\Channels\SmsChannel;
use App\Support\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CourtEventReminder extends Notification
{
    use Queueable;

    public function __construct(public CourtEvent $event)
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
            'Reminder: %s on %s — %s%s. Matter %s.',
            $event->eventTypeLabel(),
            $event->starts_at?->format('d M Y H:i'),
            $event->court?->name ?: $event->court_name ?: 'court',
            $event->case_number ? ' ('.$event->case_number.')' : '',
            $event->matter?->reference_no ?: '-'
        );

        return SmsMessage::make($content);
    }
}
