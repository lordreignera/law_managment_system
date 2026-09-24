<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leave,
        public string $stageLabel,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $leave = $this->leave->loadMissing(['user', 'leaveType']);

        return (new MailMessage)
            ->subject('Leave approval required: '.$leave->leave_no)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($leave->user?->name.' submitted a leave request that needs your review.')
            ->line('Approval stage: '.$this->stageLabel)
            ->line('Leave: '.($leave->leaveType?->name ?: 'Leave').' from '.$leave->start_date?->format('d M Y').' to '.$leave->end_date?->format('d M Y').' ('.$leave->days.' days).')
            ->action('Review leave request', route('leave.show', $leave))
            ->salutation('Regards, '.Branding::companyName());
    }
}
