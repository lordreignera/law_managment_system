<?php

namespace App\Notifications;

class StaffAccountApproved extends TemplateNotification
{
    public function __construct(private readonly string $roleName, private readonly ?string $temporaryPassword = null, private readonly bool $createdByAdmin = false) {}

    protected function eventKey(): string
    {
        return $this->createdByAdmin ? 'staff.account.created' : 'staff.account.approved';
    }

    protected function context(): array
    {
        return ['role_name' => $this->roleName];
    }

    protected function action(): array
    {
        return ['label' => 'Sign in', 'url' => route('login')];
    }

    protected function additionalMailLines(): array
    {
        return $this->temporaryPassword ? ['Temporary password: '.$this->temporaryPassword, 'Please change this password after signing in.'] : [];
    }
}
