<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salutation extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    public static function codePrefix(): string
    {
        return 'SAL';
    }
}
