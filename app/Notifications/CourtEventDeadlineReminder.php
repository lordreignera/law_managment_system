<?php

namespace App\Notifications;

use App\Models\CourtEvent;

class CourtEventDeadlineReminder extends TemplateNotification
{
    public function __construct(public CourtEvent $event) {}

    protected function eventKey(): string
    {
        return 'litigation.court_event.deadline';
    }

    protected function context(): array
    {
        return [
            'next_step' => $this->event->next_step ?: 'Court follow-up',
            'next_step_due' => $this->event->next_step_due?->format('d M Y') ?: '-',
            'case_number' => $this->event->case_number ?: '-',
            'matter_reference' => $this->event->matter?->reference_no ?: '-',
        ];
    }

    protected function action(): array
    {
        return ['label' => 'View deadline', 'url' => route('litigation.show', $this->event)];
    }
}
