<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
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

    public function engagementType()
    {
        return $this->belongsTo(EngagementType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
