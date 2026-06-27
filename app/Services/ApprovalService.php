<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ApprovalService
{
    /**
     * Open an approval request for a model. When approver ids are supplied a
     * sequential chain is created (one row per level); otherwise a single
     * open request is created that any authorised approver may decide.
     *
     * @param  array<int, int>  $approverIds
     * @return Collection<int, Approval>
     */
    public function submit(Model $approvable, ?User $requester = null, array $approverIds = []): Collection
    {
        $approvable->approvals()
            ->where('status', Approval::PENDING)
            ->update(['status' => Approval::CANCELLED]);

        $rows = collect();

        if ($approverIds === []) {
            $rows->push($approvable->approvals()->create([
                'requested_by' => $requester?->id,
                'level' => 1,
                'status' => Approval::PENDING,
            ]));
        } else {
            foreach (array_values($approverIds) as $index => $approverId) {
                $rows->push($approvable->approvals()->create([
                    'requested_by' => $requester?->id,
                    'approver_id' => $approverId,
                    'level' => $index + 1,
                    'status' => Approval::PENDING,
                ]));
            }
        }

        return $rows;
    }

    public function approve(Approval $approval, User $actor, ?string $comment = null): Approval
    {
        return $this->decide($approval, $actor, Approval::APPROVED, $comment);
    }

    public function reject(Approval $approval, User $actor, ?string $comment = null): Approval
    {
        return $this->decide($approval, $actor, Approval::REJECTED, $comment);
    }

    protected function decide(Approval $approval, User $actor, string $status, ?string $comment): Approval
    {
        $approval->forceFill([
            'status' => $status,
            'approver_id' => $actor->id,
            'comment' => $comment,
            'decided_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id' => $actor->id,
            'event' => 'approval.'.$status,
            'auditable_type' => $approval->approvable_type,
            'auditable_id' => $approval->approvable_id,
            'new_values' => [
                'level' => $approval->level,
                'comment' => $comment,
            ],
        ]);

        return $approval;
    }
}
