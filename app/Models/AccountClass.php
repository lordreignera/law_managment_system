<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountClass extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function accounts()
    {
        return $this->hasMany(ChartAccount::class);
    }
}
