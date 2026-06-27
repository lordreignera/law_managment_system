<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_branding_can_be_updated_with_a_logo(): void
    {
        $user = $this->settingsUser();
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
            ->assertSee('Test Firm Advocates')
            ->assertSee('Welcome to Test Firm')
            ->assertSee('Sign In')
            ->assertSee($setting->logo_url, false)
            ->assertDontSee('A branded workspace for matters and finance.')
            ->assertDontSee('hello@testfirm.test')
            ->assertDontSee('+256700000000')
            ->assertDontSee('Bank-level security')
            ->assertDontSee('Streamline workflow');

        $this->get('/register')
            ->assertOk()
            ->assertSee('Test Firm Advocates')
            ->assertSee('Legal Operations')
            ->assertSee('A branded workspace for matters and finance.')
            ->assertSee('Welcome to Test Firm')
            ->assertSee('hello@testfirm.test')
            ->assertSee('Reviewed Access')
            ->assertSee($setting->logo_url, false);

        File::delete(public_path($setting->logo_path));
    }

    public function test_company_logo_can_be_removed(): void
    {
        $user = $this->settingsUser();
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

    private function settingsUser(): User
    {
        $role = Role::findOrCreate('Settings Manager');
        foreach (['settings.company.edit', 'settings.company.update'] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
