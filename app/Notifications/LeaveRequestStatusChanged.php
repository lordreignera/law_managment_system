<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveRequestStatusChanged extends TemplateNotification
{
    public function __construct(public LeaveRequest $leave, public string $decision, public ?string $nextStage = null, public ?string $reviewerName = null) {}

    protected function eventKey(): string
    {
        return $this->decision === 'rejected' ? 'leave.request.rejected' : ($this->nextStage ? 'leave.request.progressed' : 'leave.request.approved');
    }

    protected function context(): array
    {
        return ['reference_no' => $this->leave->leave_no, 'stage' => $this->nextStage ?? '', 'reviewer_name' => $this->reviewerName ?: 'the reviewer', 'review_notes' => $this->leave->review_notes ?? ''];
    }

    protected function action(): array
    {
        return ['label' => $this->decision === 'rejected' ? 'Reschedule leave' : 'View leave request', 'url' => route($this->decision === 'rejected' ? 'leave.reschedule.edit' : 'leave.show', $this->leave)];
    }
}
