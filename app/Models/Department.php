<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
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
            static::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['is_active' => true]
            );
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
