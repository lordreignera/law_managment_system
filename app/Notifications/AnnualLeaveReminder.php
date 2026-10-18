<?php

namespace App\Notifications;

class AnnualLeaveReminder extends TemplateNotification
{
    public function __construct(public int $year, public float $scheduledDays, public float $requiredDays = 21) {}

    protected function eventKey(): string
    {
        return 'leave.annual.reminder';
    }

    protected function context(): array
    {
        return ['year' => $this->year, 'scheduled_days' => $this->scheduledDays, 'remaining_days' => max(0, $this->requiredDays - $this->scheduledDays)];
    }

    protected function action(): array
    {
        return ['label' => 'Schedule annual leave', 'url' => route('leave.create')];
    }
}
