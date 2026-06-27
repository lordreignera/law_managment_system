<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $branch = Branch::create(['name' => 'Kampala', 'code' => 'KLA']);
        $department = Department::create(['name' => 'Litigation', 'code' => 'LIT', 'branch_id' => $branch->id]);
        $role = Role::create(['name' => 'Advocate']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'phone' => '+256 700 123456',
            'job_title' => 'Associate',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'requested_role' => $role->name,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('status', 'Your access request has been submitted. Please wait for an administrator to review and approve your account.');

        $user = User::where('email', 'new-user@example.com')->firstOrFail()->load(['roles', 'staffProfile']);

        $this->assertFalse($user->hasRole($role->name));
        $this->assertSame('pending', $user->staffProfile->employment_status);
        $this->assertSame($role->name, $user->staffProfile->requested_role);
    }
}
