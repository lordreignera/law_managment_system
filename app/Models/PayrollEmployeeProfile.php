<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeProfile extends Model
{
    use Auditable;
    use HasFactory;

    public const TAX_RESIDENCIES = [
        'resident' => 'Resident',
        'non_resident' => 'Non-resident',
    ];

    public const PAYMENT_METHODS = [
        'bank' => 'Bank Transfer',
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
    ];

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankBranch()
    {
        return $this->belongsTo(BankBranch::class);
    }

    public function salaryRecords()
    {
        return $this->hasMany(PayrollSalaryRecord::class);
    }

    public function currentSalary()
    {
        return $this->hasOne(PayrollSalaryRecord::class)->where('status', 'active')->latestOfMany('effective_from');
    }
}
