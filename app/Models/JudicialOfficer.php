<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JudicialOfficer extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    public static function codePrefix(): string
    {
        return 'JO';
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function courtEvents()
    {
        return $this->hasMany(CourtEvent::class);
    }
}
