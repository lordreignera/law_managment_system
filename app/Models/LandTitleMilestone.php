<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitleMilestone extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'complete' => 'Complete',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_days_before' => 'integer',
    ];

    public function landTitle()
    {
        return $this->belongsTo(LandTitle::class);
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function reminderLogs()
    {
        return $this->hasMany(LandTitleMilestoneReminderLog::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at?->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
