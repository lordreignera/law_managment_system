<?php

namespace App\Models\Concerns;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Approvable
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('level');
    }

    public function currentApproval(): ?Approval
    {
        return $this->approvals()
            ->where('status', Approval::PENDING)
            ->orderBy('level')
            ->first();
    }

    public function approvalStatus(): ?string
    {
        $approvals = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();

        if ($approvals->isEmpty()) {
            return null;
        }

        if ($approvals->contains('status', Approval::REJECTED)) {
            return Approval::REJECTED;
        }

        if ($approvals->contains('status', Approval::CANCELLED)) {
            return Approval::CANCELLED;
        }

        if ($approvals->every(fn (Approval $a) => $a->status === Approval::APPROVED)) {
            return Approval::APPROVED;
        }

        return Approval::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus() === Approval::APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalStatus() === Approval::PENDING;
    }

    public function isRejected(): bool
    {
        return $this->approvalStatus() === Approval::REJECTED;
    }
}
