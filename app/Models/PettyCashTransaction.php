<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashTransaction extends Model
{
    use Auditable;
    use HasAttachments;
    use HasFactory;
    use SoftDeletes;

    public const TYPES = [
        'top_up' => 'Top-up / Float',
        'disbursement' => 'Disbursement',
    ];

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'transacted_on' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isInflow(): bool
    {
        return $this->type === 'top_up';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? str($this->type)->headline()->toString();
    }

    public static function balance(): float
    {
        return (float) self::where('type', 'top_up')->sum('amount')
            - (float) self::where('type', 'disbursement')->sum('amount');
    }
}
