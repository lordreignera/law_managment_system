<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdrResolution extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'resolved_on' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function intakeConflictParty()
    {
        return $this->belongsTo(IntakeConflictParty::class);
    }

    public function file()
    {
        return $this->hasOne(File::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
