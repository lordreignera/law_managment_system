<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AutomaticSortOrder
{
    public static function next(Builder $query, ?Model $record = null): int
    {
        if ($record?->exists) {
            return (int) ($record->getAttribute('sort_order') ?? 0);
        }

        return ((int) $query->max('sort_order')) + 1;
    }
}
