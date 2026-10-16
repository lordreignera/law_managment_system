<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRunLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'taxable_pay' => 'decimal:2',
        'employee_deductions' => 'decimal:2',
        'employer_contributions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'snapshot' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employeeProfile()
    {
        return $this->belongsTo(PayrollEmployeeProfile::class, 'payroll_employee_profile_id');
    }

    public function components()
    {
        return $this->hasMany(PayrollLineComponent::class);
    }

    public function payslip()
    {
        return $this->hasOne(PayrollPayslip::class, 'payroll_run_line_id');
    }
}
