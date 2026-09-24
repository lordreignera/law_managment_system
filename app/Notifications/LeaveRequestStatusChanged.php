<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leave,
        public string $decision,
        public ?string $nextStage = null,
        public ?string $reviewerName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $leave = $this->leave->loadMissing(['leaveType']);
        $subject = $this->decision === 'rejected'
            ? 'Leave request rejected: '.$leave->leave_no
            : ($this->nextStage ? 'Leave request moved to '.$this->nextStage.': '.$leave->leave_no : 'Leave request approved: '.$leave->leave_no);

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name.',');

        if ($this->decision === 'rejected') {
            $message->line('Your leave request has been rejected by '.($this->reviewerName ?: 'the reviewer').'.');
            if ($leave->review_notes) {
                $message->line('Reviewer notes: '.$leave->review_notes);
            }
            $message->action('Reschedule leave', route('leave.reschedule.edit', $leave));
        } elseif ($this->nextStage) {
            $message
                ->line('Your leave request passed the previous approval stage.')
                ->line('It is now awaiting: '.$this->nextStage.'.')
                ->action('View leave request', route('leave.show', $leave));
        } else {
            $message
                ->line('Your leave request has received all required approvals.')
                ->action('View leave request', route('leave.show', $leave));
        }

        return $message->salutation('Regards, '.Branding::companyName());
    }
}
