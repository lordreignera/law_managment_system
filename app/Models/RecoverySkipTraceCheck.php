<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoverySkipTraceCheck extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'result' => 'array',
        'checked_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(RecoveryAccount::class, 'recovery_account_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
