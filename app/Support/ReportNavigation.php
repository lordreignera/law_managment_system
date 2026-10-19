<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ReportNavigation
{
    public static function categories(): Collection
    {
        return collect(config('reporting.navigation_groups', []));
    }

    public static function categoryForGroup(?string $group): ?string
    {
        foreach (self::categories() as $slug => $category) {
            if (in_array($group, $category['groups'], true)) {
                return $slug;
            }
        }

        return null;
    }
}
