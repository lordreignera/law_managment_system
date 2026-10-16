<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitleMilestoneReminderLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'target_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function milestone()
    {
        return $this->belongsTo(LandTitleMilestone::class, 'land_title_milestone_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
