<?php

namespace App\Notifications;

use App\Models\CourtEvent;

class CourtEventReminder extends TemplateNotification
{
    public function __construct(public CourtEvent $event) {}

    protected function eventKey(): string
    {
        return 'litigation.court_event.reminder';
    }

    protected function context(): array
    {
        return ['event_type' => $this->event->eventTypeLabel(), 'starts_at' => $this->event->starts_at?->format('d M Y H:i'), 'court_name' => $this->event->court?->name ?: ($this->event->court_name ?: 'Court'), 'case_number' => $this->event->case_number ?: '-', 'matter_reference' => $this->event->matter?->reference_no ?: '-'];
    }

    protected function action(): array
    {
        return ['label' => 'View court event', 'url' => route('litigation.show', $this->event)];
    }
}
