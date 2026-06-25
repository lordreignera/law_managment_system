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
            'logo_path' => null,
            'tagline' => 'Firm Management System',
            'login_heading' => 'Secure access for your firm operations.',
            'login_subheading' => 'Manage matters, recoveries, land titles, finance, staff, and approvals from one workspace.',
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

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
