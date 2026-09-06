<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Schema;

class Branding
{
    public static function companyName(): string
    {
        if (Schema::hasTable('company_settings')) {
            return CompanySetting::current()->company_name ?: 'Kalikumutima & Co Advocates';
        }

        return config('app.name', 'Kalikumutima & Co Advocates');
    }

    public static function logoUrl(): ?string
    {
        if (Schema::hasTable('company_settings')) {
            return CompanySetting::current()->logo_url;
        }

        $defaultLogoPath = CompanySetting::defaults()['logo_path'] ?? null;

        if ($defaultLogoPath && file_exists(public_path($defaultLogoPath))) {
            return asset($defaultLogoPath);
        }

        return null;
    }
}
