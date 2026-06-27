<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtEvent extends Model
{
    use Auditable;
    use HasAttachments;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'adjourned' => 'Adjourned',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const EVENT_TYPES = [
        'mention' => 'Mention',
        'hearing' => 'Hearing',
        'ruling' => 'Ruling',
        'judgment' => 'Judgment',
        'conference' => 'Conference',
        'filing_deadline' => 'Filing Deadline',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_step_due' => 'date',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function eventTypeLabel(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? str($this->event_type)->headline()->toString();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'scheduled' && $this->starts_at !== null && $this->starts_at->isPast();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['scheduled', 'adjourned']);
    }
}
