<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Notifications\BrandedResetPassword;
use App\Notifications\StaffAccountApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_approval_email_uses_kalikumutima_branding(): void
    {
        CompanySetting::current();

        $user = User::factory()->create([
            'name' => 'Norah Nakamatte',
        ]);

        $html = (string) (new StaffAccountApproved('Recovery Officer', 'secret123', true))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('Kalikumutima &amp; Co Advocates', $html);
        $this->assertStringContainsString('Kali%20Logo%202.png', $html);
        $this->assertStringContainsString('All rights reserved.', $html);
        $this->assertStringNotContainsString('Laravel Logo', $html);
        $this->assertStringNotContainsString('Law Management System', $html);
    }

    public function test_password_reset_email_uses_kalikumutima_branding(): void
    {
        CompanySetting::current();

        $user = User::factory()->create([
            'name' => 'Finance User',
        ]);

        $html = (string) (new BrandedResetPassword('reset-token'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('Kalikumutima &amp; Co Advocates', $html);
        $this->assertStringContainsString('Kali%20Logo%202.png', $html);
        $this->assertStringContainsString('All rights reserved.', $html);
        $this->assertStringNotContainsString('Laravel Logo', $html);
        $this->assertStringNotContainsString('Law Management System', $html);
    }
}
