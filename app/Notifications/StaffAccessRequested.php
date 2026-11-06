<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class StaffAccessRequested extends Notification
{
    public function __construct(private readonly User $applicant) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => 'staff.access.requested',
            'subject' => 'New staff access request',
            'body' => $this->applicant->name.' has registered and is waiting for approval.',
            'url' => route('access.approvals.index'),
            'user_id' => $this->applicant->id,
        ];
    }
}
