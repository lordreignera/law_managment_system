<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSalaryComponent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function salaryRecord()
    {
        return $this->belongsTo(PayrollSalaryRecord::class, 'payroll_salary_record_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
