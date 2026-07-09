<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\InstructionType;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\ZonalOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_setting_records_can_be_created_with_company_codes(): void
    {
        $user = $this->settingsUser();
        CompanySetting::current()->update(['initials' => 'KC']);

        $response = $this->actingAs($user)->post(route('settings.system.store', 'instruction-types'), [
            'code' => 'MANUAL-CODE',
            'name' => 'Fresh Client Instruction',
            'description' => 'Instruction received directly from a client.',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.system.index', 'instruction-types', absolute: false));

        $this->assertDatabaseHas('instruction_types', [
            'name' => 'Fresh Client Instruction',
            'code' => 'KC-IT-0001',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('instruction_types', [
            'code' => 'MANUAL-CODE',
        ]);
    }

    public function test_system_setting_records_can_be_updated_and_deleted(): void
    {
        $user = $this->settingsUser();
        $record = InstructionType::create([
            'name' => 'Old Instruction',
            'description' => 'Old wording.',
            'is_active' => true,
        ]);

        $this->actingAs($user)->put(route('settings.system.update', ['instruction-types', $record]), [
            'name' => 'Updated Instruction',
            'description' => 'Updated wording.',
            'sort_order' => 3,
        ])->assertRedirect(route('settings.system.index', 'instruction-types', absolute: false));

        $this->assertDatabaseHas('instruction_types', [
            'id' => $record->id,
            'name' => 'Updated Instruction',
            'sort_order' => 3,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('settings.system.destroy', ['instruction-types', $record]))
            ->assertRedirect();

        $this->assertDatabaseMissing('instruction_types', [
            'id' => $record->id,
        ]);
    }

    public function test_zonal_offices_and_bank_branches_can_be_managed_from_settings(): void
    {
        $user = $this->settingsUser();
        $bank = Bank::create(['name' => 'Stanbic Bank', 'code' => 'BNK-001', 'is_active' => true]);

        $this->actingAs($user)->post(route('settings.system.store', 'zonal-offices'), [
            'name' => 'KCCA',
            'office_location' => 'City Hall, Wing B',
            'districts_covered' => 'Kampala',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('settings.system.index', 'zonal-offices', absolute: false));

        $this->assertDatabaseHas('zonal_offices', [
            'name' => 'KCCA',
            'office_location' => 'City Hall, Wing B',
            'districts_covered' => 'Kampala',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('settings.system.store', 'bank-branches'), [
            'bank_id' => $bank->id,
            'name' => 'Kampala Road Branch',
            'office_location' => 'Kampala Road',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('settings.system.index', 'bank-branches', absolute: false));

        $this->assertDatabaseHas('bank_branches', [
            'bank_id' => $bank->id,
            'name' => 'Kampala Road Branch',
            'office_location' => 'Kampala Road',
            'is_active' => true,
        ]);

        $this->assertSame('KCCA', ZonalOffice::first()->name);
        $this->assertSame('Kampala Road Branch', BankBranch::first()->name);
    }

    private function settingsUser(): User
    {
        $role = Role::findOrCreate('Settings Manager');
        foreach ([
            'settings.system.overview', 'settings.system.index',
            'settings.system.create', 'settings.system.store',
            'settings.system.edit', 'settings.system.update',
            'settings.system.destroy',
            'settings.company.edit', 'settings.company.update',
        ] as $permissionName) {
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
