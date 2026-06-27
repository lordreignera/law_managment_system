<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryClient extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    public static function codePrefix(): string
    {
        return 'RC';
    }

    public function accounts()
    {
        return $this->hasMany(RecoveryAccount::class);
    }
}
