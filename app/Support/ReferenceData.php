<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class ReferenceData
{
    public static function jobTitleRule(?string $current = null)
    {
        return Rule::exists('job_titles', 'name')->where(fn ($query) => $query
            ->where(fn ($query) => $query->where('is_active', true)->when($current, fn ($query) => $query->orWhere('name', $current))));
    }

    public static function currencyRule()
    {
        return Rule::exists('currency_types', 'iso_code')->where('is_active', true);
    }
}
