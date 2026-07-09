<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function defaults(): array
    {
        return [
            'company_name' => 'Kalikumutima & Co Advocates',
            'short_name' => 'KFMS',
            'initials' => 'K',
            'logo_path' => 'admin/assets/images/Kali Logo 2.png',
            'tagline' => 'Firm Management System',
            'login_heading' => '',
            'login_subheading' => 'Manage matters, recoveries, securities, finance, staff, and approvals from one workspace.',
            'primary_color' => '#050505',
            'secondary_color' => '#ffffff',
            'contact_email' => null,
            'contact_phone' => null,
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaultLogoPaths(): array
    {
        return [
            'admin/assets/images/Kali Logo 2.png',
            'uploads/company-logos/Kali Logo 2.png',
            'uploads/company-logos/kali-logo-2.png',
            'uploads/company-logos/Kali Logo.png',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->logo_path ?: static::defaults()['logo_path'];

        if ($logoPath && file_exists(public_path($logoPath))) {
            return asset($logoPath);
        }

        foreach (static::defaultLogoPaths() as $fallbackLogoPath) {
            if (file_exists(public_path($fallbackLogoPath))) {
                return asset($fallbackLogoPath);
            }
        }

        return null;
    }
}
