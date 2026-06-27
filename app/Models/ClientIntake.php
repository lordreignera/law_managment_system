<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientIntake extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'inquiry' => 'Inquiry',
        'consultation' => 'Consultation',
        'conflict_check' => 'Conflict Check',
        'engagement_pending' => 'Engagement Pending',
        'rejected' => 'Rejected',
    ];

    public const CONFLICT_STATUSES = [
        'pending' => 'Pending Review',
        'cleared' => 'Cleared',
        'conflict_found' => 'Conflict Found',
        'more_information_needed' => 'More Information Needed',
    ];

    public const URGENCIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'urgent' => 'Urgent',
        'critical' => 'Critical',
    ];

    protected $guarded = [];

    protected $casts = [
        'consultation_on' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function practiceArea()
    {
        return $this->belongsTo(PracticeArea::class);
    }

    public function preferredLawyer()
    {
        return $this->belongsTo(User::class, 'preferred_lawyer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedMatter()
    {
        return $this->belongsTo(Matter::class, 'converted_matter_id');
    }

    public function conflictParties()
    {
        return $this->hasMany(IntakeConflictParty::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function conflictStatusLabel(): string
    {
        return self::CONFLICT_STATUSES[$this->conflict_status] ?? str($this->conflict_status)->headline()->toString();
    }
}
