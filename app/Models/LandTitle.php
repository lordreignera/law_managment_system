<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BranchScoped;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitle extends Model
{
    use Auditable;
    use BranchScoped;
    use HasAttachments;
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'received' => 'Received',
        'in_progress' => 'In Progress',
        'dispatched' => 'Dispatched',
        'returned' => 'Returned',
        'closed' => 'Closed',
    ];

    protected $guarded = [];

    protected $casts = [
        'instruction_date' => 'date',
        'received_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankBranch()
    {
        return $this->belongsTo(BankBranch::class);
    }

    public function zonalOffice()
    {
        return $this->belongsTo(ZonalOffice::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function movements()
    {
        return $this->hasMany(LandTitleMovement::class)->latest('moved_at');
    }

    public function checklistItems()
    {
        return $this->hasMany(LandTitleChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function milestones()
    {
        return $this->hasMany(LandTitleMilestone::class)->orderBy('due_at')->orderBy('sort_order');
    }

    public function canTransitionTo(string $status): bool
    {
        $allowed = [
            'pending' => ['pending', 'received', 'in_progress', 'dispatched'],
            'received' => ['received', 'in_progress', 'dispatched'],
            'in_progress' => ['in_progress', 'dispatched', 'received'],
            'dispatched' => ['dispatched', 'returned'],
            'returned' => ['returned', 'closed', 'in_progress'],
            'closed' => ['closed'],
        ];

        return in_array($status, $allowed[$this->status] ?? [], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
