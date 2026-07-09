<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_main_and_finance_dashboards_render(): void
    {
        $role = Role::findOrCreate('Dashboard Viewer');
        foreach (['dashboard', 'finance.dashboard', 'messages.index'] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);

        $conversation = Conversation::create([
            'created_by' => $user->id,
            'audience_type' => 'self',
            'title' => 'Dashboard Message Preview',
            'last_message_at' => now(),
        ]);
        $conversation->participants()->create(['user_id' => $user->id]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => 'This should appear in the dashboard messages panel.',
            'sent_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operations Snapshot')
            ->assertSee('Dashboard Message Preview');

        $this->actingAs($user)->get(route('finance.dashboard'))->assertOk();
    }
}
