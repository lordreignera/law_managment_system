<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use Auditable;
    use BranchScoped;
    use HasFactory;
    use SoftDeletes;

    public const TYPES = [
        'meeting' => 'Meeting',
        'appointment' => 'Appointment',
        'reminder' => 'Reminder',
        'task' => 'Task / Deadline',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'reminded_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'calendar_event_branch')->withTimestamps();
    }

    public function scopeForBranchOf(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->whereNull($this->getTable().'.branch_id');

            if ($user->branch_id) {
                $query->orWhere($this->getTable().'.branch_id', $user->branch_id)
                    ->orWhereHas('branches', fn (Builder $query) => $query->where('branches.id', $user->branch_id));
            }
        });
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'calendar_event_user')->withTimestamps();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? str($this->type)->headline()->toString();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function branchLabel(): string
    {
        if ($this->relationLoaded('branches') && $this->branches->isNotEmpty()) {
            return $this->branches->pluck('name')->join(', ');
        }

        return $this->branch?->name ?: 'Firm-wide';
    }
}
