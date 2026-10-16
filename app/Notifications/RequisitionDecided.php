<?php

namespace App\Notifications;

use App\Models\Requisition;

class RequisitionDecided extends TemplateNotification
{
    public function __construct(public Requisition $requisition) {}

    protected function eventKey(): string
    {
        return 'requisition.decided';
    }

    protected function context(): array
    {
        return ['reference_no' => $this->requisition->reference_no, 'decision' => $this->requisition->status, 'review_notes' => $this->requisition->review_notes ?? ''];
    }

    protected function action(): array
    {
        return ['label' => 'View requisition', 'url' => route('requisitions.show', $this->requisition)];
    }
}
