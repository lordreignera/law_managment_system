<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'annual_entitlement_days' => 'float',
        'carry_forward' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function codePrefix(): string
    {
        return 'LVT';
    }
}
