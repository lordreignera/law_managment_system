<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matter extends Model
{
    use BranchScoped;
    use HasFactory;
    use HasAttachments;
    use SoftDeletes;

    public const STATUSES = [
        'inquiry' => 'Inquiry',
        'consultation' => 'Consultation',
        'conflict_check' => 'Conflict Check',
        'file_pending' => 'File Pending',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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

    public function file()
    {
        return $this->hasOne(File::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
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

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date');
    }

    public function letters()
    {
        return $this->hasMany(LegalLetter::class)->latest('letter_date');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->latest('spent_on');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
