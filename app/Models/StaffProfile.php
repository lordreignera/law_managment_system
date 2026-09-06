<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    public const STAFF_NUMBER_PREFIX = 'ST-KA';

    protected $guarded = [];

    protected $casts = [
        'joined_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    protected static function booted(): void
    {
        static::creating(function (StaffProfile $profile) {
            if (! filled($profile->staff_no)) {
                $profile->staff_no = self::nextStaffNumber();
            }
        });
    }

    public static function nextStaffNumber(): string
    {
        $latest = self::query()
            ->where('staff_no', 'like', self::STAFF_NUMBER_PREFIX.'-%')
            ->orderByDesc('staff_no')
            ->value('staff_no');

        $next = 1;

        if (is_string($latest) && preg_match('/^'.preg_quote(self::STAFF_NUMBER_PREFIX, '/').'-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $staffNo = self::STAFF_NUMBER_PREFIX.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (self::where('staff_no', $staffNo)->exists());

        return $staffNo;
    }
}
