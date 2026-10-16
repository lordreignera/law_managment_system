<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRule extends Model
{
    use Auditable;
    use HasFactory;

    public const TYPES = [
        'paye' => 'PAYE',
        'nssf' => 'NSSF',
        'custom' => 'Custom Rule',
    ];

    public const METHODS = [
        'percentage' => 'Percentage',
        'fixed' => 'Fixed Amount',
        'bands' => 'Tax Bands (JSON)',
        'formula' => 'Custom Formula',
    ];

    public const FLAGS = [
        'active' => 'Active',
        'employer_cost' => 'Employer cost (do not deduct)',
    ];

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:4',
        'configuration' => 'array',
        'is_employer_cost' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public static function nextCode(): string
    {
        $number = static::query()->pluck('code')
            ->map(fn ($code) => (int) str($code)->after('RULE-'))
            ->max() ?: 0;

        return 'RULE-'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->rule_type] ?? str($this->rule_type)->headline()->toString();
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->calculation_method] ?? str($this->calculation_method)->headline()->toString();
    }
}
