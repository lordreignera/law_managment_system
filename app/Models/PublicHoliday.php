<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    /**
     * Holidays that fall inside the given month, expanding recurring
     * (annual fixed-date) holidays onto the requested year.
     *
     * @return \Illuminate\Support\Collection<string, string> keyed by Y-m-d => name
     */
    public static function forMonth(int $year, int $month): \Illuminate\Support\Collection
    {
        return static::all()
            ->mapWithKeys(function (self $holiday) use ($year) {
                $date = $holiday->is_recurring
                    ? $holiday->date->copy()->setYear($year)
                    : $holiday->date;

                return [$date->toDateString() => $holiday->name];
            })
            ->filter(fn ($name, $date) => \Illuminate\Support\Carbon::parse($date)->month === $month
                && \Illuminate\Support\Carbon::parse($date)->year === $year);
    }
}
