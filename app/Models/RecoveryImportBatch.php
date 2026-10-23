<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryImportBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'total_principal' => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'import_options' => 'array',
        'preview_rows' => 'array',
        'preview_counts' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(RecoveryClient::class, 'recovery_client_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accounts()
    {
        return $this->hasMany(RecoveryAccount::class);
    }
}
