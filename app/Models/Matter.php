<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matter extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'inquiry' => 'Inquiry',
        'consultation' => 'Consultation',
        'conflict_check' => 'Conflict Check',
        'engagement_pending' => 'Engagement Pending',
        'open' => 'Open',
        'planning' => 'Planning',
        'active' => 'Active',
        'waiting_for_client' => 'Waiting for Client',
        'waiting_for_third_party_or_court' => 'Waiting for Third Party/Court',
        'billing_pending' => 'Billing Pending',
        'under_review' => 'Under Review',
        'closed' => 'Closed',
        'archived' => 'Archived',
    ];

    protected $guarded = [];

    protected $casts = [
        'opened_on' => 'date',
        'closed_on' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function ultimateClient()
    {
        return $this->belongsTo(Client::class, 'ultimate_client_id');
    }

    public function practiceArea()
    {
        return $this->belongsTo(PracticeArea::class);
    }

    public function businessIndustry()
    {
        return $this->belongsTo(BusinessIndustry::class);
    }

    public function matterCategory()
    {
        return $this->belongsTo(MatterCategory::class);
    }

    public function engagement()
    {
        return $this->hasOne(Engagement::class);
    }

    public function engagements()
    {
        return $this->hasMany(Engagement::class);
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }

    public function assignments()
    {
        return $this->hasMany(MatterAssignment::class);
    }

    public function courtEvents()
    {
        return $this->hasMany(CourtEvent::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
