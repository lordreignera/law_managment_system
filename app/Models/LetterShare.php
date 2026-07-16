<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterShare extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function letter()
    {
        return $this->belongsTo(LegalLetter::class, 'legal_letter_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sharer()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
