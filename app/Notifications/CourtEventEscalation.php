<?php

namespace App\Notifications;

use App\Models\CourtEvent;

class CourtEventEscalation extends TemplateNotification
{
    public function __construct(public CourtEvent $event) {}

    protected function eventKey(): string
    {
        return 'litigation.court_event.escalated';
    }

    protected function context(): array
    {
        return [
            'event_type' => $this->event->eventTypeLabel(),
            'matter_reference' => $this->event->matter?->reference_no ?: '-',
            'next_step' => $this->event->next_step ?: 'Review overdue court work',
            'case_number' => $this->event->case_number ?: '-',
        ];
    }

    protected function action(): array
    {
        return ['label' => 'Review court work', 'url' => route('litigation.show', $this->event)];
    }
}
