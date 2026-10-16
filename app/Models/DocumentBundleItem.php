<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentBundleItem extends Model
{
    use Auditable;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(DocumentBundle::class, 'document_bundle_id');
    }

    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }
}
