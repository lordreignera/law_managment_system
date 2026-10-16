<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollLineComponent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'is_taxable' => 'boolean',
        'is_employer_cost' => 'boolean',
        'metadata' => 'array',
    ];

    public function line()
    {
        return $this->belongsTo(PayrollRunLine::class, 'payroll_run_line_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
