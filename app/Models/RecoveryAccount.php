<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecoveryAccount extends Model
{
    use BranchScoped;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'active' => 'Active',
        'closed' => 'Closed',
        'written_off' => 'Written Off',
    ];

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'assigned_at' => 'datetime',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'amount_recovered' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(RecoveryClient::class, 'recovery_client_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function activities()
    {
        return $this->hasMany(RecoveryActivity::class)->latest('activity_at');
    }

    /**
     * Recompute the recovered total from logged activity payments.
     */
    public function recomputeRecovered(): void
    {
        $this->amount_recovered = (float) $this->activities()->sum('amount_paid');
        $this->save();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
