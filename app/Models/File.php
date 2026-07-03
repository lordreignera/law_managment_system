<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasAttachments;
    use HasFactory;

    public const RETAINER_PAYMENT_SOURCES = Expense::PAYMENT_SOURCES;

    protected $guarded = [];

    protected $casts = [
        'agreed_fee_amount' => 'decimal:2',
        'engagement_letter_sent_on' => 'date',
        'fee_agreement_sent_on' => 'date',
        'retainer_required' => 'boolean',
        'retainer_amount' => 'decimal:2',
        'client_accepted_on' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function adrResolution()
    {
        return $this->belongsTo(AdrResolution::class);
    }

    public function billingType()
    {
        return $this->belongsTo(BillingType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function retainerPaymentSourceLabel(): string
    {
        return self::RETAINER_PAYMENT_SOURCES[$this->retainer_payment_source] ?? str($this->retainer_payment_source)->headline()->toString();
    }
}
