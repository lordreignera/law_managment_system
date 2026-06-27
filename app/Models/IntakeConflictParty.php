<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntakeConflictParty extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function intake()
    {
        return $this->belongsTo(ClientIntake::class, 'client_intake_id');
    }
}
