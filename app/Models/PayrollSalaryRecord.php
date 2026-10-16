<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSalaryRecord extends Model
{
    use Auditable;
    use HasFactory;

    public const FREQUENCIES = [
        'monthly' => 'Monthly',
        'weekly' => 'Weekly',
        'biweekly' => 'Bi-weekly',
        'daily' => 'Daily',
    ];

    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employeeProfile()
    {
        return $this->belongsTo(PayrollEmployeeProfile::class, 'payroll_employee_profile_id');
    }

    public function currencyType()
    {
        return $this->belongsTo(CurrencyType::class);
    }

    public function components()
    {
        return $this->hasMany(PayrollSalaryComponent::class);
    }
}
