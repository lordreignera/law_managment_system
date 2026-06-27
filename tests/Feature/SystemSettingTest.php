<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\InstructionType;
use App\Models\StaffProfile;
use App\Models\User;
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

    private function settingsUser(): User
    {
        $role = Role::findOrCreate('Settings Manager');
        $role->givePermissionTo(Permission::findOrCreate('manage settings'));

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
