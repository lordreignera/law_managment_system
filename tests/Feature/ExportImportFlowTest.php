<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CourtEvent;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExportImportFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function operator(): User
    {
        $role = Role::findOrCreate('Registry Operator');
        foreach ([
            'clients.export', 'clients.import',
            'matters.export', 'matters.import',
            'litigation.export', 'litigation.import',
        ] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);

        return $user;
    }

    public function test_registers_can_be_exported(): void
    {
        $user = $this->operator();

        $this->actingAs($user)->get(route('clients.export'))->assertOk();
        $this->actingAs($user)->get(route('matters.export'))->assertOk();
        $this->actingAs($user)->get(route('litigation.export'))->assertOk();
    }

    public function test_clients_can_be_imported(): void
    {
        $user = $this->operator();

        $csv = "client_type,name,email,phone\n"
            ."individual,Imported Person,imported@example.test,+256700000123\n"
            ."organization,,,\n"; // second row skipped (no name/org)

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $this->actingAs($user)
            ->post(route('clients.import'), ['file' => $file])
            ->assertRedirect(route('clients.index', absolute: false));

        $this->assertDatabaseHas('clients', ['name' => 'Imported Person', 'email' => 'imported@example.test']);
        $this->assertSame(1, Client::count());
    }

    public function test_matters_can_be_imported_one_per_client(): void
    {
        $user = $this->operator();
        PracticeArea::create(['name' => 'Litigation', 'is_active' => true]);

        $client = Client::create([
            'client_no' => 'CL26070001',
            'client_type' => 'individual',
            'name' => 'Matter Client',
            'status' => 'active',
        ]);

        $csv = "client_no,title,practice_area,status\n"
            ."CL26070001,Imported Matter,Litigation,open\n"
            ."CL26070001,Duplicate Matter,Litigation,open\n"; // skipped: client already has a matter

        $file = UploadedFile::fake()->createWithContent('matters.csv', $csv);

        $this->actingAs($user)
            ->post(route('matters.import'), ['file' => $file])
            ->assertRedirect(route('matters.index', absolute: false));

        $this->assertSame(1, Matter::where('client_id', $client->id)->count());
        $this->assertDatabaseHas('matters', ['title' => 'Imported Matter', 'client_id' => $client->id]);
    }

    public function test_litigation_events_can_be_imported(): void
    {
        $user = $this->operator();

        $client = Client::create([
            'client_no' => 'CL26070002',
            'client_type' => 'individual',
            'name' => 'Court Client',
            'status' => 'active',
        ]);

        $matter = Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT26070001',
            'title' => 'Court Matter',
            'status' => 'open',
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'For litigation import.',
        ]);

        $csv = "matter_ref,court_name,event_type,starts_at\n"
            ."MT26070001,High Court,mention,2026-08-01 09:00\n"
            ."UNKNOWN,High Court,mention,2026-08-01 09:00\n"; // skipped: unknown matter

        $file = UploadedFile::fake()->createWithContent('litigation.csv', $csv);

        $this->actingAs($user)
            ->post(route('litigation.import'), ['file' => $file])
            ->assertRedirect(route('litigation.index', absolute: false));

        $this->assertSame(1, CourtEvent::where('matter_id', $matter->id)->count());
        $this->assertDatabaseHas('court_events', ['matter_id' => $matter->id, 'court_name' => 'High Court']);
    }
}
