<?php

namespace App\Notifications;

use App\Models\Requisition;

class RequisitionSubmitted extends TemplateNotification
{
    public function __construct(public Requisition $requisition) {}

    protected function eventKey(): string
    {
        return 'requisition.submitted';
    }

    protected function context(): array
    {
        return ['reference_no' => $this->requisition->reference_no, 'user_name' => $this->requisition->requester?->name, 'amount' => number_format($this->requisition->amount, 2), 'purpose' => $this->requisition->purpose];
    }

    protected function action(): array
    {
        return ['label' => 'Review requisition', 'url' => route('requisitions.show', $this->requisition)];
    }
}
