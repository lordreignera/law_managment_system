<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class MonthlyReferenceNumber
{
    public static function make(string $modelClass, string $column, string $prefix): string
    {
        /** @var class-string<Model> $modelClass */
        $period = now()->format('ym');
        $fullPrefix = strtoupper($prefix).$period;

        $lastNumber = $modelClass::where($column, 'like', "{$fullPrefix}%")
            ->pluck($column)
            ->map(fn ($reference) => (int) str_replace($fullPrefix, '', $reference))
            ->max() ?? 0;

        return $fullPrefix.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
