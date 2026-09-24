<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnualLeaveReminder extends Notification
{
    use Queueable;

    public function __construct(
        public int $year,
        public float $scheduledDays,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $remaining = max(0, 21 - $this->scheduledDays);

        return (new MailMessage)
            ->subject('Annual leave reminder for '.$this->year)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Please schedule your '.$this->year.' annual leave plan of 21 days.')
            ->line('Currently scheduled: '.$this->scheduledDays.' days. Remaining to schedule: '.$remaining.' days.')
            ->line('Requests must pass department manager, HR/administrator, and superadmin/CEO review before the leave begins.')
            ->action('Schedule annual leave', route('leave.create'))
            ->salutation('Regards, '.Branding::companyName());
    }
}
