<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPayslip extends Model
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function line()
    {
        return $this->belongsTo(PayrollRunLine::class, 'payroll_run_line_id');
    }
}
