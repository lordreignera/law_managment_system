<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceDocumentBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        CompanySetting::query()->firstOrCreate(['id' => 1], array_merge(CompanySetting::defaults(), [
            'contact_email' => 'info@kalikumutima.com',
            'contact_phone' => '+256 390 902 922',
        ]));
    }

    public function test_invoice_and_fee_note_documents_are_branded(): void
    {
        $user = $this->staffUser([
            'finance.invoices.documents.show',
            'finance.invoices.documents.pdf',
            'matters.billing.show',
        ]);

        $invoice = $this->invoice();

        $this->actingAs($user)
            ->get(route('finance.invoices.documents.show', [$invoice, 'invoice']))
            ->assertOk()
            ->assertSee('Kalikumutima &amp; Co Advocates', false)
            ->assertSee('admin/assets/images/Kali Logo 2.png', false)
            ->assertSee('Invoice')
            ->assertSee('INV-001')
            ->assertSee('Bill To')
            ->assertSee('UGX 1,180,000.00');

        $this->actingAs($user)
            ->get(route('finance.invoices.documents.show', [$invoice, 'fee-note']))
            ->assertOk()
            ->assertSee('Fee Note')
            ->assertSee('Professional fee note for legal services');

        $this->actingAs($user)
            ->get(route('matters.billing.show', $invoice->matter))
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee('Fee Note');
    }

    public function test_branded_invoice_can_be_downloaded_as_pdf(): void
    {
        $user = $this->staffUser(['finance.invoices.documents.pdf']);
        $invoice = $this->invoice();

        $this->actingAs($user)
            ->get(route('finance.invoices.documents.pdf', [$invoice, 'invoice']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function invoice(): Invoice
    {
        $client = Client::create([
            'client_no' => 'CL-001',
            'client_type' => 'individual',
            'name' => 'Demo Client',
            'email' => 'client@example.test',
            'phone' => '+256700000001',
            'address' => 'Kampala',
            'tin' => '100000001',
            'status' => 'active',
        ]);

        $matter = Matter::create([
            'client_id' => $client->id,
            'title' => 'Commercial Advisory',
            'reference_no' => 'MT-001',
            'opened_on' => now()->toDateString(),
            'status' => 'open',
            'privacy_status' => 'public',
        ]);

        return Invoice::create([
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'invoice_no' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => 1000000,
            'tax' => 180000,
            'total' => 1180000,
            'paid_amount' => 0,
            'status' => 'sent',
        ]);
    }

    private function staffUser(array $permissions): User
    {
        $role = Role::findOrCreate('Finance Officer', 'web');

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
