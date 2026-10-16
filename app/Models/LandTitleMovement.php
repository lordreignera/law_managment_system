<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitleMovement extends Model
{
    use Auditable;
    use HasFactory;

    public const TYPES = [
        'received' => 'Received',
        'dispatched' => 'Dispatched',
        'returned' => 'Returned',
        'transferred' => 'Transferred',
    ];

    protected $guarded = [];

    protected $casts = [
        'moved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function landTitle()
    {
        return $this->belongsTo(LandTitle::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->movement_type] ?? str($this->movement_type)->headline()->toString();
    }
}
