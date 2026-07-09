<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Schema;

class Branding
{
    public static function companyName(): string
    {
        if (Schema::hasTable('company_settings')) {
            return CompanySetting::current()->company_name ?: 'JurisFlow';
        }

        return config('app.name', 'JurisFlow');
    }
}
