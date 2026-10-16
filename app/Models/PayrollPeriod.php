<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'processing' => 'Processing',
        'awaiting_approval' => 'Awaiting Approval',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'payment_on' => 'date',
        'closed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function run()
    {
        return $this->hasOne(PayrollRun::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public static function nextCode(): string
    {
        $prefix = 'PAY-'.now()->format('Ym').'-';
        $last = static::query()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $number = $last ? ((int) str($last)->afterLast('-')) + 1 : 1;

        return $prefix.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
