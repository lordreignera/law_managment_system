<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccountApproved extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $roleName,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Branding::companyName();

        return (new MailMessage)
            ->subject('Your account has been approved')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your account has been approved and activated.')
            ->line('Assigned role: '.$this->roleName)
            ->line('You can now sign in and start using your firm workspace.')
            ->action('Sign in', route('login'))
            ->salutation('Regards, '.$company);
    }
}
