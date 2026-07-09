<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitle extends Model
{
    use HasAttachments;
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'received' => 'Received',
        'in_progress' => 'In Progress',
        'dispatched' => 'Dispatched',
        'returned' => 'Returned',
        'closed' => 'Closed',
    ];

    protected $guarded = [];

    protected $casts = [
        'instruction_date' => 'date',
        'received_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankBranch()
    {
        return $this->belongsTo(BankBranch::class);
    }

    public function zonalOffice()
    {
        return $this->belongsTo(ZonalOffice::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
