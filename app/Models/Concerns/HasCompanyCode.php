<?php

namespace App\Models\Concerns;

use App\Models\CompanySetting;

trait HasCompanyCode
{
    protected static function bootHasCompanyCode(): void
    {
        static::creating(function ($model) {
            $model->code = static::nextCompanyCode();
        });
    }

    public static function nextCompanyCode(): string
    {
        $companyInitials = CompanySetting::current()->initials ?: 'K';
        $companyInitials = preg_replace('/[^A-Za-z0-9]/', '', $companyInitials) ?: 'K';
        $prefix = strtoupper($companyInitials).'-'.static::codePrefix();

        $lastNumber = static::query()
            ->where('code', 'like', $prefix.'-%')
            ->pluck('code')
            ->map(function ($code) use ($prefix) {
                return (int) str_replace($prefix.'-', '', $code);
            })
            ->max() ?? 0;

        return $prefix.'-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
