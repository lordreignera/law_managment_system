<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartAccount extends Model
{
    use HasFactory;

    public const ACCOUNT_TYPES = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expense',
    ];

    public const NORMAL_BALANCES = [
        'debit' => 'Debit',
        'credit' => 'Credit',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_postable' => 'boolean',
        'is_bank_account' => 'boolean',
        'is_cash_account' => 'boolean',
        'is_client_funds_account' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function accountClass()
    {
        return $this->belongsTo(AccountClass::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('account_number');
    }

    public function mappings()
    {
        return $this->hasMany(FinanceAccountMapping::class);
    }

    public function typeLabel(): string
    {
        return self::ACCOUNT_TYPES[$this->account_type] ?? str($this->account_type)->headline()->toString();
    }

    public function normalBalanceLabel(): string
    {
        return self::NORMAL_BALANCES[$this->normal_balance] ?? str($this->normal_balance)->headline()->toString();
    }

    public function fullName(): string
    {
        return trim($this->account_number.' - '.$this->name);
    }

    public static function nextNumber(?int $accountClassId = null, ?int $parentId = null): string
    {
        if ($parentId) {
            $parent = self::find($parentId);
            if (! $parent) {
                return '1000';
            }

            $increment = match (true) {
                $parent->level <= 1 && str_ends_with($parent->account_number, '000') => 100,
                $parent->level <= 1 => 10,
                default => 1,
            };

            $maxChild = self::where('parent_id', $parent->id)
                ->pluck('account_number')
                ->filter(fn ($number) => ctype_digit((string) $number))
                ->map(fn ($number) => (int) $number)
                ->max();

            $candidate = (int) ($maxChild ?: $parent->account_number) + $increment;

            while (self::where('account_number', (string) $candidate)->exists()) {
                $candidate += $increment;
            }

            return (string) $candidate;
        }

        $accountClass = $accountClassId ? AccountClass::find($accountClassId) : AccountClass::orderBy('sort_order')->first();
        $prefix = $accountClass?->code_prefix ?: '1';
        $base = (int) ($prefix.'000');

        $maxRoot = self::where('account_class_id', $accountClass?->id)
            ->pluck('account_number')
            ->filter(fn ($number) => ctype_digit((string) $number))
            ->map(fn ($number) => (int) $number)
            ->max();

        $candidate = $maxRoot ? ((int) (floor($maxRoot / 100) + 1) * 100) : $base;

        while (self::where('account_number', (string) $candidate)->exists()) {
            $candidate += 100;
        }

        return (string) $candidate;
    }
}
