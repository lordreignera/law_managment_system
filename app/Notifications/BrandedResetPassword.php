<?php

namespace App\Notifications;

use App\Support\Branding;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class BrandedResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);
        $company = Branding::companyName();

        return (new MailMessage)
            ->subject('Reset your account password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your account.')
            ->line('Use the secure link below to set a new password. This link will expire soon for your protection.')
            ->action('Reset Password', $resetUrl)
            ->line('If you did not request a password reset, you can safely ignore this email.')
            ->salutation('Regards, '.$company);
    }
}
