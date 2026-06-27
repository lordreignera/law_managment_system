<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    public static function codePrefix(): string
    {
        return 'DP';
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
