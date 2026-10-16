<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveApprovalRequested extends TemplateNotification
{
    public function __construct(public LeaveRequest $leave, public string $stageLabel) {}

    protected function eventKey(): string
    {
        return 'leave.request.submitted';
    }

    protected function context(): array
    {
        return ['user_name' => $this->leave->user?->name, 'reference_no' => $this->leave->leave_no, 'leave_type' => $this->leave->leaveType?->name, 'start_date' => $this->leave->start_date?->format('d M Y'), 'end_date' => $this->leave->end_date?->format('d M Y'), 'days' => $this->leave->days, 'stage' => $this->stageLabel];
    }

    protected function action(): array
    {
        return ['label' => 'Review leave request', 'url' => route('leave.show', $this->leave)];
    }
}
