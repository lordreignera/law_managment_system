<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_branding_can_be_updated_with_a_logo(): void
    {
        $user = User::factory()->create();
        CompanySetting::current();

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'company_name' => 'Test Firm Advocates',
            'short_name' => 'TFA',
            'initials' => 'TF',
            'tagline' => 'Legal Operations',
            'login_heading' => 'Welcome to Test Firm',
            'login_subheading' => 'A branded workspace for matters and finance.',
            'primary_color' => '#111111',
            'secondary_color' => '#f8f8f8',
            'contact_email' => 'hello@testfirm.test',
            'contact_phone' => '+256700000000',
            'logo' => UploadedFile::fake()->image('logo.png', 80, 80),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Company branding updated.');

        $setting = CompanySetting::current()->fresh();

        $this->assertSame('Test Firm Advocates', $setting->company_name);
        $this->assertSame('TFA', $setting->short_name);
        $this->assertSame('#111111', $setting->primary_color);
        $this->assertNotNull($setting->logo_path);
        $this->assertFileExists(public_path($setting->logo_path));

        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome to Test Firm')
            ->assertSee($setting->logo_url, false);

        File::delete(public_path($setting->logo_path));
    }

    public function test_company_logo_can_be_removed(): void
    {
        $user = User::factory()->create();
        $logoPath = 'uploads/company-logos/test-remove-logo.png';

        File::ensureDirectoryExists(dirname(public_path($logoPath)));
        File::put(public_path($logoPath), 'test-logo');

        CompanySetting::current()->update([
            'logo_path' => $logoPath,
        ]);

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'company_name' => 'No Logo Advocates',
            'short_name' => 'NLA',
            'initials' => 'NL',
            'tagline' => 'Legal Operations',
            'login_heading' => 'Welcome without logo',
            'login_subheading' => 'Initials are shown when no logo exists.',
            'primary_color' => '#000000',
            'secondary_color' => '#ffffff',
            'contact_email' => null,
            'contact_phone' => null,
            'remove_logo' => '1',
        ]);

        $response->assertRedirect();

        $setting = CompanySetting::current()->fresh();

        $this->assertNull($setting->logo_path);
        $this->assertFileDoesNotExist(public_path($logoPath));
    }
}
