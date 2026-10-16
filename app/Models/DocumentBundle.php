<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentBundle extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'finalized' => 'Finalized',
    ];

    protected $guarded = [];

    protected $casts = [
        'finalized_at' => 'datetime',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(DocumentBundleItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
