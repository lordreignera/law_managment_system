<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\AdrResolution;
use App\Models\BillingType;
use App\Models\Branch;
use App\Models\BusinessIndustry;
use App\Models\ContactPosition;
use App\Models\Country;
use App\Models\File;
use App\Models\InstructionType;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\OpposingCounsel;
use App\Models\PracticeArea;
use App\Models\RelationshipType;
use App\Models\Salutation;
use App\Models\Shelf;
use App\Models\User;
use App\Exports\ClientsExport;
use App\Imports\ClientsImport;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->forBranchOf($request->user())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('client_no', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('organization_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('client_type'), fn ($query) => $query->where('client_type', $request->string('client_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.clients.index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'client_type', 'status']),
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new ClientsExport($request->user()), 'clients-'.now()->format('Ymd-His').'.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new ClientsImport($request->user());
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('clients.index')
            ->with('status', "Imported {$import->imported} client(s); skipped {$import->skipped}.");
    }

    public function create()
    {
        return redirect()
            ->route('intakes.create')
            ->with('status', 'Start with a client intake so conflicts are checked before a client record is approved.');
    }

    public function show(Request $request, Client $client)
    {
        abort_unless(Client::forBranchOf($request->user())->whereKey($client->id)->exists(), 403);

        $client->load([
            'clientInCharge',
            'country',
            'position',
            'salutation',
            'nextOfKin.relationshipType',
            'nextOfKin.salutation',
            'nextOfKin.country',
        ]);

        if ($request->boolean('modal')) {
            $client->loadCount(['matters', 'files', 'adrResolutions']);

            return view('modules.clients.partials.preview', compact('client'));
        }

        $client->load([
            'contacts.relationshipType',
            'matter',
            'matters.practiceArea',
            'files.billingType',
            'files.matter',
            'adrResolutions.file.billingType',
            'adrResolutions.file.matter',
        ]);

        $sourceIntake = $client->intakes()
            ->with('conflictParties')
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->latest()
            ->first();
        $modalAdr = $client->adrResolutions->firstWhere('id', (int) old('adr_resolution_id'));

        return view('modules.clients.show', [
            'client' => $client,
            'modalAdr' => $modalAdr,
            'sourceIntake' => $sourceIntake,
            'conflictParties' => $sourceIntake?->conflictParties ?? collect(),
            'adrNumber' => MonthlyReferenceNumber::make(AdrResolution::class, 'adr_no', 'ADR'),
            'suggestedTitle' => $sourceIntake?->legal_issue,
            'fileNumber' => MonthlyReferenceNumber::make(File::class, 'file_number', 'FL'),
            'billingTypes' => BillingType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'paymentSources' => File::RETAINER_PAYMENT_SOURCES,
            'fileMatters' => Matter::visibleTo($request->user())
                ->where('client_id', $client->id)
                ->latest()
                ->get(['id', 'reference_no', 'title']),
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'instructionTypes' => InstructionType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'opposingCounsels' => OpposingCounsel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::query()
                ->where(fn ($query) => $query->whereNull('account_type')->orWhere('account_type', 'staff'))
                ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->with('roles')
                ->orderBy('name')
                ->get(),
            'availableFiles' => $client->files()->whereNull('matter_id')->orderBy('file_name')->get(),
        ]);
    }

    public function destroy(Request $request, Client $client)
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);
        abort_unless(Client::forBranchOf($request->user())->whereKey($client->id)->exists(), 404);

        DB::transaction(function () use ($client) {
            // A removed client's portal login must stop working immediately.
            $client->portalAccount()->update(['is_active' => false]);
            $client->delete();
        });

        return redirect()
            ->route('clients.index')
            ->with('status', $client->display_name.' was removed from the active register. Its history and audit log have been retained.');
    }

    public function editDetails(Client $client)
    {
        abort_unless(Client::forBranchOf(request()->user())->whereKey($client->id)->exists(), 403);

        return view('modules.clients.details', [
            'client' => $client->load('nextOfKin'),
            'salutations' => Salutation::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'positions' => ContactPosition::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'countries' => Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'relationships' => RelationshipType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function updateDetails(Request $request, Client $client)
    {
        $data = $request->validate([
            'client_type' => ['required', Rule::in(['individual', 'organization'])],
            'is_prospect' => ['nullable', 'boolean'],
            'salutation_id' => ['nullable', 'exists:salutations,id'],
            'position_id' => ['nullable', 'exists:contact_positions,id'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'client_in_charge_id' => ['nullable', 'exists:users,id'],
            'organization_name' => ['required_if:client_type,organization', 'nullable', 'string', 'max:191'],
            'first_name' => ['required_if:client_type,individual', 'nullable', 'string', 'max:191'],
            'last_name' => ['required_if:client_type,individual', 'nullable', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'gender' => ['required_if:client_type,individual', 'nullable', Rule::in(['female', 'male'])],
            'nin_passport_no' => ['nullable', 'string', 'max:191'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:1000'],
            'occupation' => ['nullable', 'string', 'max:191'],
            'tin' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'add_next_of_kin' => ['nullable', 'boolean'],
            'next_of_kin.relationship_type_id' => ['required_if:add_next_of_kin,1', 'nullable', 'exists:relationship_types,id'],
            'next_of_kin.salutation_id' => ['nullable', 'exists:salutations,id'],
            'next_of_kin.country_id' => ['nullable', 'exists:countries,id'],
            'next_of_kin.first_name' => ['required_if:add_next_of_kin,1', 'nullable', 'string', 'max:191'],
            'next_of_kin.last_name' => ['required_if:add_next_of_kin,1', 'nullable', 'string', 'max:191'],
            'next_of_kin.middle_name' => ['nullable', 'string', 'max:191'],
            'next_of_kin.gender' => ['nullable', Rule::in(['female', 'male'])],
            'next_of_kin.phone' => ['nullable', 'string', 'max:60'],
            'next_of_kin.email' => ['nullable', 'email', 'max:191'],
            'next_of_kin.nin_passport_no' => ['nullable', 'string', 'max:191'],
            'next_of_kin.date_of_birth' => ['nullable', 'date'],
            'next_of_kin.address' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $client, $data) {
            $clientData = collect($data)->except(['add_next_of_kin', 'next_of_kin'])->toArray();
            $clientData['is_prospect'] = $request->boolean('is_prospect');

            if ($clientData['client_type'] === 'organization') {
                $clientData['name'] = $clientData['organization_name'];
                $clientData['salutation_id'] = null;
                $clientData['first_name'] = null;
                $clientData['middle_name'] = null;
                $clientData['last_name'] = null;
                $clientData['gender'] = null;
                $clientData['date_of_birth'] = null;
            } else {
                $clientData['organization_name'] = null;
                $clientData['name'] = $this->clientName($clientData);
            }

            $client->update($clientData);

            if ($request->boolean('add_next_of_kin')) {
                $nextOfKin = $data['next_of_kin'] ?? [];
                $nextOfKin['contact_type'] = 'next_of_kin';
                $nextOfKin['is_primary'] = true;
                $nextOfKin['name'] = trim(collect([
                    $nextOfKin['first_name'] ?? null,
                    $nextOfKin['middle_name'] ?? null,
                    $nextOfKin['last_name'] ?? null,
                ])->filter()->implode(' '));

                $client->contacts()->updateOrCreate(
                    ['contact_type' => 'next_of_kin'],
                    $nextOfKin
                );
            }
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client details updated.');
    }

    public function store(Request $request)
    {
        return redirect()
            ->route('intakes.create')
            ->with('status', 'Direct client creation is closed. Record the enquiry as an intake, complete conflict screening, and approve it to create the client record.');
    }

    private function clientName(array $data): string
    {
        if (($data['client_type'] ?? '') === 'organization') {
            return $data['organization_name'];
        }

        return trim(collect([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ])->filter()->implode(' '));
    }
}
