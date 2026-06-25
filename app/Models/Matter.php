<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matter extends Model
{
    use HasFactory;
    use SoftDeletes;

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
}
