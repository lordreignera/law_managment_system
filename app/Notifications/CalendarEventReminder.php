<?php

namespace App\Notifications;

use App\Models\CalendarEvent;

class CalendarEventReminder extends TemplateNotification
{
    public function __construct(public CalendarEvent $event) {}

    protected function eventKey(): string
    {
        return 'calendar.event.reminder';
    }

    protected function context(): array
    {
        return ['title' => $this->event->title, 'starts_at' => $this->event->starts_at?->format($this->event->all_day ? 'd M Y' : 'd M Y H:i'), 'location' => $this->event->location ?: '-'];
    }

    protected function action(): array
    {
        return ['label' => 'View event', 'url' => route('calendar.show', $this->event)];
    }
}
