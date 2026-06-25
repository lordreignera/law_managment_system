<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitle extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'instruction_date' => 'date',
        'received_on' => 'date',
        'dispatched_on' => 'date',
        'returned_on' => 'date',
    ];
}
