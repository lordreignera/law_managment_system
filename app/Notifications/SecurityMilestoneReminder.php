<?php

namespace App\Notifications;

use App\Models\LandTitleMilestone;

class SecurityMilestoneReminder extends TemplateNotification
{
    public function __construct(public LandTitleMilestone $milestone, public string $reminderType = 'upcoming') {}

    protected function eventKey(): string
    {
        return 'securities.milestone.reminder';
    }

    protected function context(): array
    {
        $title = $this->milestone->landTitle;

        return [
            'milestone' => $this->milestone->name,
            'reference_no' => $title?->reference_no ?: '-',
            'due_at' => $this->milestone->due_at?->format('d M Y H:i') ?: '-',
            'borrower_name' => $title?->borrower_name ?: '-',
            'status' => $this->reminderType === 'overdue' ? 'Overdue' : 'Upcoming',
        ];
    }

    protected function action(): ?array
    {
        return $this->milestone->landTitle
            ? ['label' => 'View security', 'url' => route('securities.show', $this->milestone->landTitle)]
            : null;
    }
}
