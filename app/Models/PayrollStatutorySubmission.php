<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollStatutorySubmission extends Model
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_on' => 'date',
        'filed_on' => 'date',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function filer()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
