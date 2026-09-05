<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryActivity extends Model
{
    use HasAttachments;
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

    public const OUTCOMES = [
        'no_response' => 'No response',
        'paid' => 'Client paid',
        'promised' => 'Promised to pay',
        'refused' => 'Refused to pay',
        'disputed' => 'Disputed debt',
        'needs_follow_up' => 'Needs follow-up',
        'other' => 'Other report',
    ];

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'promised_on' => 'date',
        'promised_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'outstanding_balance_after' => 'decimal:2',
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

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->response_outcome] ?? ucfirst((string) $this->response_outcome);
    }
}
