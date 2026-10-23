<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientIntake extends Model
{
    use HasFactory;
    use Auditable;
    use SoftDeletes;

    public const STATUSES = [
        'pending_review' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'more_information_needed' => 'More Information Needed',
    ];

    public const REVIEW_DECISIONS = [
        'pending' => 'Pending Review',
        'approved' => 'Approve Client',
        'rejected' => 'Reject Client',
        'more_information_needed' => 'More Information Needed',
    ];

    public const CONFLICT_STATUSES = [
        'pending' => 'Not checked',
        'cleared' => 'Cleared',
        'potential_conflict' => 'Potential conflict',
        'conflict_found' => 'Conflict found',
    ];

    public const URGENCIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'urgent' => 'Urgent',
        'critical' => 'Critical',
    ];

    public const REFERRAL_SOURCES = [
        'walk_in' => 'Walk-in',
        'phone_call' => 'Phone Call',
        'email' => 'Email',
        'website' => 'Website',
        'existing_client' => 'Existing Client',
        'staff_referral' => 'Staff Referral',
        'social_media' => 'Social Media',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = [
        'consultation_on' => 'date',
        'reviewed_at' => 'datetime',
        'conflict_checked_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
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

    public function conflictChecker()
    {
        return $this->belongsTo(User::class, 'conflict_checked_by');
    }

    public function conflictParties()
    {
        return $this->hasMany(IntakeConflictParty::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function reviewDecisionLabel(): string
    {
        return self::REVIEW_DECISIONS[$this->review_decision] ?? str($this->review_decision)->headline()->toString();
    }

    public function conflictStatusLabel(): string
    {
        return self::CONFLICT_STATUSES[$this->conflict_status] ?? str($this->conflict_status)->headline()->toString();
    }

    public function referralSourceLabel(): string
    {
        return self::REFERRAL_SOURCES[$this->referral_source] ?? str($this->referral_source)->headline()->toString();
    }
}
