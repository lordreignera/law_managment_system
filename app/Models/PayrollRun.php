<?php

namespace App\Models;

use App\Models\Concerns\Approvable;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use Approvable;
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'calculated' => 'Calculated',
        'awaiting_approval' => 'Awaiting Approval',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'voided' => 'Voided',
    ];

    protected $guarded = [];

    protected $casts = [
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'totals' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines()
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function paymentBatch()
    {
        return $this->hasOne(PayrollPaymentBatch::class);
    }

    public function statutorySubmissions()
    {
        return $this->hasMany(PayrollStatutorySubmission::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
