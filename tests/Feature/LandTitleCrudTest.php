<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\LandTitle;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\ZonalOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LandTitleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_security_can_be_created_viewed_updated_deleted_and_exported(): void
    {
        Storage::fake('local');

        $role = Role::findOrCreate('Securities Manager', 'web');
        foreach ([
            'land-titles.index',
            'land-titles.create',
            'land-titles.store',
            'land-titles.show',
            'land-titles.edit',
            'land-titles.update',
            'land-titles.return',
            'land-titles.destroy',
            'land-titles.export',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $bank = Bank::create([
            'name' => 'Test Bank',
            'code' => 'TBK',
            'is_active' => true,
        ]);

        $bankBranch = BankBranch::create([
            'bank_id' => $bank->id,
            'name' => 'Kampala Road Branch',
            'code' => 'KRB',
            'office_location' => 'Kampala Road',
            'is_active' => true,
        ]);

        $zonalOffice = ZonalOffice::create([
            'name' => 'Kampala Zonal Office',
            'code' => 'KZO',
            'office_location' => 'City Hall',
            'districts_covered' => 'Kampala',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('land-titles.create'))
            ->assertOk()
            ->assertSee('Add Security');

        $this->actingAs($user)
            ->post(route('land-titles.store'), [
                'bank_id' => $bank->id,
                'bank_branch_id' => $bankBranch->id,
                'zonal_office_id' => $zonalOffice->id,
                'handled_by' => $user->id,
                'borrower_name' => 'Jane Borrower',
                'instruction_type' => 'Mortgage registration',
                'instruction_date' => now()->toDateString(),
                'received_from' => 'Kampala Road Branch',
                'received_at' => '2026-07-09T09:30',
                'status' => 'pending',
                'notes' => 'Original title received.',
                'documents' => [
                    UploadedFile::fake()->create('security-title.pdf', 64, 'application/pdf'),
                ],
            ])
            ->assertSessionHas('status', 'Security registered.');

        $title = LandTitle::first();

        $this->assertNotNull($title);
        $this->assertMatchesRegularExpression('/^SEC\d{8}$/', $title->reference_no);
        $this->assertSame(1, $title->attachments()->count());

        $this->actingAs($user)
            ->get(route('land-titles.show', $title))
            ->assertOk()
            ->assertSee('Jane Borrower')
            ->assertSee('Test Bank')
            ->assertSee('Kampala Road Branch')
            ->assertSee('security-title.pdf');

        $this->actingAs($user)
            ->put(route('land-titles.update', $title), [
                'bank_id' => $bank->id,
                'bank_branch_id' => $bankBranch->id,
                'zonal_office_id' => $zonalOffice->id,
                'handled_by' => $user->id,
                'borrower_name' => 'Jane Borrower Updated',
                'instruction_type' => 'Mortgage release',
                'instruction_date' => now()->toDateString(),
                'received_from' => 'Kampala Road Branch',
                'returned_to' => 'Kampala Road Branch',
                'received_at' => '2026-07-09T09:30',
                'dispatched_at' => '2026-07-09T14:00',
                'returned_at' => '2026-07-10T10:15',
                'status' => 'returned',
                'notes' => 'Security returned.',
            ])
            ->assertSessionHas('status', 'Security updated.');

        $this->assertSame('returned', $title->fresh()->status);

        $this->actingAs($user)
            ->patch(route('land-titles.return', $title), [
                'returned_to' => 'Kampala Road Branch',
                'returned_at' => '2026-07-11T11:30',
                'notes' => 'Returned with signed acknowledgment.',
            ])
            ->assertSessionHas('status', 'Security marked as returned.');

        $this->assertSame('Kampala Road Branch', $title->fresh()->returned_to);
        $this->assertSame('returned', $title->fresh()->status);

        $this->actingAs($user)
            ->get(route('land-titles.export'))
            ->assertOk();

        $this->actingAs($user)
            ->delete(route('land-titles.destroy', $title))
            ->assertRedirect(route('land-titles.index', absolute: false))
            ->assertSessionHas('status', 'Security deleted.');

        $this->assertDatabaseMissing('land_titles', ['id' => $title->id]);
    }
}
