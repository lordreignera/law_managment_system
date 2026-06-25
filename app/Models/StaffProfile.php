<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'joined_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
