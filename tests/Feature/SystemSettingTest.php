<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\InstructionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_setting_records_can_be_created_with_company_codes(): void
    {
        $user = User::factory()->create();
        CompanySetting::current()->update(['initials' => 'KC']);

        $response = $this->actingAs($user)->post(route('settings.system.store', 'instruction-types'), [
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
    }

    public function test_system_setting_records_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
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
}
