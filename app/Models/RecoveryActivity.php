<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryActivity extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'promised_on' => 'date',
    ];
}
