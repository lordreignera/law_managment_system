<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function staffUser(array $permissions): User
    {
        $role = Role::findOrCreate('Audit Viewer');
        $role->syncPermissions(collect($permissions)->map(fn ($permission) => Permission::findOrCreate($permission)));

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_authorized_staff_can_filter_and_inspect_a_sanitized_audit_entry(): void
    {
        $actor = $this->staffUser(['audit.index', 'audit.show']);

        AuditLog::create([
            'user_id' => $actor->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $actor->id,
            'old_values' => ['name' => 'Old name', 'password' => 'old-password'],
            'new_values' => ['name' => 'New name', 'api_token' => 'should-not-be-visible'],
            'url' => 'http://localhost/users/'.$actor->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Audit test browser',
        ]);

        $this->actingAs($actor)
            ->get(route('audit.index', ['event' => 'updated', 'search' => '127.0.0.1']))
            ->assertOk()
            ->assertSee('Audit log')
            ->assertSee('Updated')
            ->assertSee('1')
            ->assertSee('127.0.0.1')
            ->assertSee('data-bs-target="#audit-details-', false)
            ->assertSee('kfms-audit-modal')
            ->assertDontSee('href="'.route('audit.show', AuditLog::first()).'"', false);

        $this->actingAs($actor)
            ->get(route('audit.show', AuditLog::firstOrFail()))
            ->assertOk()
            ->assertSee('Old name')
            ->assertSee('New name')
            ->assertSee('[redacted]')
            ->assertDontSee('old-password')
            ->assertDontSee('should-not-be-visible');
    }

    public function test_audit_log_is_denied_without_the_audit_permission(): void
    {
        $actor = $this->staffUser([]);

        $this->actingAs($actor)
            ->get(route('audit.index'))
            ->assertForbidden();
    }
}
