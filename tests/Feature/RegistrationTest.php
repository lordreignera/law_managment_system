<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
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
        JobTitle::create(['name' => 'Associate']);

        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'new-user@example.com',
            'phone' => '+256 700 123456',
            'date_of_birth' => '1990-05-15',
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
        $response->assertSessionHas('status', 'Your access request has been submitted. Verify your email, then wait for an administrator to approve your account.');

        $user = User::where('email', 'new-user@example.com')->firstOrFail()->load(['roles', 'staffProfile']);

        $this->assertFalse($user->hasRole($role->name));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('pending', $user->staffProfile->employment_status);
        $this->assertSame('1990-05-15', $user->staffProfile->date_of_birth?->toDateString());
        $this->assertSame($role->name, $user->staffProfile->requested_role);
        $this->assertMatchesRegularExpression('/^ST-KA-\d{5}$/', $user->staffProfile->staff_no);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->post('/login', [
            'email' => 'new-user@example.com',
            'password' => 'password',
        ])
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('approval_pending', true);

        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Access Approval Pending')
            ->assertSee('approval email');
    }

    public function test_registration_rejects_a_department_from_another_branch(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $kampala = Branch::create(['name' => 'Kampala', 'code' => 'KLA']);
        $mbale = Branch::create(['name' => 'Mbale', 'code' => 'MBL']);
        $department = Department::create(['name' => 'Mbale Finance', 'code' => 'MB-FIN', 'branch_id' => $mbale->id, 'is_active' => true]);
        $role = Role::create(['name' => 'Advocate']);
        JobTitle::create(['name' => 'Associate']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Wrong Branch User',
            'email' => 'wrong-branch@example.com',
            'phone' => '+256 700 123456',
            'job_title' => 'Associate',
            'branch_id' => $kampala->id,
            'department_id' => $department->id,
            'requested_role' => $role->name,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertRedirect('/register')
            ->assertSessionHasErrors('department_id');
        $this->assertDatabaseMissing('users', ['email' => 'wrong-branch@example.com']);
    }

    public function test_registration_notifies_active_approval_reviewers(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $branch = Branch::create(['name' => 'Mbale', 'code' => 'MBL']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN', 'branch_id' => $branch->id]);
        $role = Role::create(['name' => 'Accountant']);
        JobTitle::create(['name' => 'Accounts Officer']);

        $reviewer = User::factory()->create(['name' => 'Approval Reviewer']);
        $reviewer->givePermissionTo(Permission::findOrCreate('access.users.approve', 'web'));
        StaffProfile::create([
            'user_id' => $reviewer->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employment_status' => 'active',
        ]);

        Notification::fake();

        $this->post('/register', [
            'name' => 'Pending Staff Member',
            'email' => 'pending-staff@example.com',
            'phone' => '+256 700 123456',
            'job_title' => 'Accounts Officer',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'requested_role' => $role->name,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        Notification::assertSentTo($reviewer, \App\Notifications\StaffAccessRequested::class);
    }
}
