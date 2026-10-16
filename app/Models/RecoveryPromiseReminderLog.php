<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryPromiseReminderLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'scheduled_for' => 'date',
        'sent_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function activity()
    {
        return $this->belongsTo(RecoveryActivity::class, 'recovery_activity_id');
    }

    public function account()
    {
        return $this->belongsTo(RecoveryAccount::class, 'recovery_account_id');
    }
}
