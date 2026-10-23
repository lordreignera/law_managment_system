<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'tax_inclusive' => 'boolean',
        'tax_rate' => 'decimal:4',
    ];

    public function getBalanceAttribute(): float
    {
        return max((float) $this->total - (float) $this->paid_amount, 0);
    }

    public function statusLabel(): string
    {
        return str($this->status)->headline()->toString();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class)->latest('paid_on');
    }

    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class);
    }
}
