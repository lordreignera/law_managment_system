<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecoveryImportTemplate extends Model
{
    protected $guarded = [];

    protected $casts = ['column_map' => 'array'];

    public function client()
    {
        return $this->belongsTo(RecoveryClient::class, 'recovery_client_id');
    }
}
