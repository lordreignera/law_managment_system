<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use App\Support\AutomaticSortOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    public static function codePrefix(): string
    {
        return 'DP';
    }

    /**
     * Departments that every branch starts with. New departments added later
     * remain local to the branch where they are created.
     *
     * @return list<string>
     */
    public static function standardNames(): array
    {
        return [
            'Litigation',
            'Recoveries',
            'Securities',
            'Finance and Administration',
            'Human Resources',
        ];
    }

    public static function seedStandardForBranch(Branch $branch): void
    {
        foreach (static::standardNames() as $name) {
            $department = static::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['is_active' => true]
            );

            if (! $department->wasRecentlyCreated || (int) $department->sort_order !== 0) {
                continue;
            }

            $department->update([
                'sort_order' => AutomaticSortOrder::next(
                    static::where('branch_id', $branch->id)->whereKeyNot($department->id)
                ),
            ]);
        }
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
