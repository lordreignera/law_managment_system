<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryActivity extends Model
{
    use HasFactory;

    public const TYPES = [
        'call' => 'Phone Call',
        'visit' => 'Field Visit',
        'sms' => 'SMS / Message',
        'email' => 'Email',
        'payment' => 'Payment Received',
        'promise' => 'Promise to Pay',
        'legal' => 'Legal Action',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'promised_on' => 'date',
        'promised_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(RecoveryAccount::class, 'recovery_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->activity_type] ?? ucfirst((string) $this->activity_type);
    }
}
