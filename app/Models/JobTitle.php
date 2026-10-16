<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updated(function (JobTitle $title) {
            if ($title->wasChanged('name')) {
                StaffProfile::where('job_title', $title->getOriginal('name'))->update(['job_title' => $title->name]);
            }
        });
    }

    public static function codePrefix(): string
    {
        return 'JOB';
    }
}
