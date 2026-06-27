<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable;
    use HasAttachments;
    use HasFactory;
    use SoftDeletes;

    public const PAYMENT_SOURCES = [
        'bank' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
    ];

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'spent_on' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function requisition()
    {
        return $this->belongsTo(Requisition::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function paymentSourceLabel(): string
    {
        return self::PAYMENT_SOURCES[$this->payment_source] ?? str($this->payment_source)->headline()->toString();
    }
}
