<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPaymentBatch extends Model
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'exported_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
