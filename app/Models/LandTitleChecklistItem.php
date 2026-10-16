<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandTitleChecklistItem extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'complete' => 'Complete',
        'waived' => 'Waived',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function landTitle()
    {
        return $this->belongsTo(LandTitle::class);
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function evidenceAttachment()
    {
        return $this->belongsTo(Attachment::class, 'evidence_attachment_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
