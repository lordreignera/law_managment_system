<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'present' => 'Present',
        'late' => 'Late',
        'incomplete' => 'Incomplete',
        'corrected' => 'Corrected',
    ];

    protected $guarded = [];

    protected $casts = [
        'attendance_date' => 'date',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
