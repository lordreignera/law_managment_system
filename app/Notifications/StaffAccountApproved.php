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
        private readonly ?string $temporaryPassword = null,
        private readonly bool $createdByAdmin = false,
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

        $message = (new MailMessage)
            ->subject($this->createdByAdmin ? 'Your firm account is ready' : 'Your account has been approved')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->createdByAdmin ? 'Your firm account has been created and activated.' : 'Your account has been approved and activated.')
            ->line('Assigned role: '.$this->roleName)
            ->line('You can now sign in and start using your firm workspace.');

        if ($this->temporaryPassword) {
            $message
                ->line('Temporary password: '.$this->temporaryPassword)
                ->line('Please change this password after signing in.');
        }

        return $message
            ->action('Sign in', route('login'))
            ->salutation('Regards, '.$company);
    }
}
