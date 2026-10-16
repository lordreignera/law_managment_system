<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    use Auditable;
    use HasFactory;

    public const TYPES = [
        'earning' => 'Earning',
        'deduction' => 'Deduction',
        'tax' => 'Tax',
        'employer_contribution' => 'Employer Contribution',
    ];

    public const METHODS = [
        'fixed' => 'Fixed Amount',
        'percentage' => 'Percentage',
        'formula' => 'Custom Formula',
    ];

    public const BASES = [
        'basic_salary' => 'Basic Salary',
        'gross_pay' => 'Gross Pay',
        'taxable_pay' => 'Taxable Pay',
        'net_pay' => 'Net Pay',
        'total_deductions' => 'Total Deductions',
        'custom' => 'Custom / Formula',
    ];

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'is_taxable' => 'boolean',
        'is_nssf_liable' => 'boolean',
        'is_employer_cost' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function salaryComponents()
    {
        return $this->hasMany(PayrollSalaryComponent::class);
    }

    public function lineComponents()
    {
        return $this->hasMany(PayrollLineComponent::class);
    }

    public static function nextCode(): string
    {
        $number = static::query()->pluck('code')
            ->map(fn ($code) => (int) str($code)->after('PAY-'))
            ->max() ?: 0;

        return 'PAY-'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->component_type] ?? str($this->component_type)->headline()->toString();
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->calculation_method] ?? str($this->calculation_method)->headline()->toString();
    }
}
