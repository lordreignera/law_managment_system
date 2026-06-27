<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a query scope that restricts records to the viewer's branch.
 *
 * Records whose branch_id is null are treated as firm-wide and remain
 * visible to everyone. Users who can see all branches bypass the filter.
 */
trait BranchScoped
{
    public function scopeForBranchOf(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->canSeeAllBranches()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->whereNull($this->getTable().'.branch_id');

            if ($user->branch_id) {
                $query->orWhere($this->getTable().'.branch_id', $user->branch_id);
            }
        });
    }
}
