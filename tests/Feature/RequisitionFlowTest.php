<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionCategory;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequisitionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_staff_can_submit_requisition_and_it_awaits_approval(): void
    {
        $category = RequisitionCategory::create(['name' => 'Office Purchase']);
        $staff = $this->activeUser(['requisitions.index', 'requisitions.create', 'requisitions.store', 'requisitions.show'], 'Advocate');

        $response = $this->actingAs($staff)->post(route('requisitions.store'), [
            'requisition_category_id' => $category->id,
            'purpose' => 'Printer toner',
            'amount' => 250000,
        ]);

        $this->assertDatabaseHas('requisitions', [
            'requested_by' => $staff->id,
            'status' => 'submitted',
            'purpose' => 'Printer toner',
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => Requisition::class,
            'status' => 'pending',
        ]);

        $response->assertRedirect(route('requisitions.show', Requisition::first()));
    }

    public function test_staff_only_sees_their_own_requisitions(): void
    {
        $staff = $this->activeUser(['requisitions.index', 'requisitions.show'], 'Advocate');
        $other = $this->activeUser(['requisitions.index'], 'Paralegal');

        Requisition::create([
            'reference_no' => 'RQ2606-0001', 'requested_by' => $other->id,
            'purpose' => 'Secret purchase', 'amount' => 100, 'status' => 'submitted',
        ]);

        $this->actingAs($staff)->get(route('requisitions.index'))->assertOk()->assertDontSee('RQ2606-0001');
    }

    public function test_approver_can_approve_requisition(): void
    {
        $category = RequisitionCategory::create(['name' => 'Office Purchase']);
        $staff = $this->activeUser(['requisitions.store'], 'Advocate');
        $approver = $this->activeUser(['requisitions.index', 'requisitions.show', 'requisitions.approve', 'requisitions.reject'], 'Accountant');

        $this->actingAs($staff)->post(route('requisitions.store'), [
            'requisition_category_id' => $category->id,
            'purpose' => 'Stationery',
            'amount' => 50000,
        ])->assertRedirect();

        $requisition = Requisition::first();

        $this->actingAs($approver)
            ->patch(route('requisitions.approve', $requisition), ['review_notes' => 'OK'])
            ->assertRedirect();

        $this->assertSame('approved', $requisition->fresh()->status);
        $this->assertSame('approved', $requisition->fresh()->approvalStatus());
    }

    public function test_non_approver_cannot_access_approve_route(): void
    {
        $staff = $this->activeUser(['requisitions.store', 'requisitions.show'], 'Advocate');

        $this->actingAs($staff)->post(route('requisitions.store'), [
            'purpose' => 'Transport',
            'amount' => 30000,
        ]);

        $requisition = Requisition::first();

        $this->actingAs($staff)
            ->patch(route('requisitions.approve', $requisition), [])
            ->assertForbidden();
    }
}
