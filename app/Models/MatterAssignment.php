<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatterAssignment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'assigned_on' => 'date',
        'is_lead' => 'boolean',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
