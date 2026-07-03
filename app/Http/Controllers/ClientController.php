<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Branch;
use App\Models\ContactPosition;
use App\Models\Country;
use App\Models\RelationshipType;
use App\Models\Salutation;
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
        return view('modules.clients.create', [
            'clientNumber' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'salutations' => Salutation::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'positions' => ContactPosition::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'countries' => Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'relationships' => RelationshipType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Client $client)
    {
        return view('modules.clients.show', [
            'client' => $client->load([
                'clientInCharge',
                'country',
                'position',
                'salutation',
                'nextOfKin.relationshipType',
                'nextOfKin.salutation',
                'nextOfKin.country',
                'contacts',
                'matter',
                'matters.practiceArea',
                'files.billingType',
                'files.matter',
                'adrResolutions.file.billingType',
                'adrResolutions.file.matter',
            ]),
        ]);
    }

    public function editDetails(Client $client)
    {
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
        $data = $request->validate([
            'client_type' => ['required', Rule::in(['individual', 'organization'])],
            'is_prospect' => ['nullable', 'boolean'],
            'salutation_id' => ['nullable', 'exists:salutations,id'],
            'position_id' => ['required', 'exists:contact_positions,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'client_in_charge_id' => ['nullable', 'exists:users,id'],
            'organization_name' => ['required_if:client_type,organization', 'nullable', 'string', 'max:191'],
            'first_name' => ['required_if:client_type,individual', 'nullable', 'string', 'max:191'],
            'last_name' => ['required_if:client_type,individual', 'nullable', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'gender' => ['required_if:client_type,individual', 'nullable', Rule::in(['female', 'male'])],
            'nin_passport_no' => ['nullable', 'string', 'max:191'],
            'date_of_birth' => ['nullable', 'date'],
            'occupation' => ['nullable', 'string', 'max:191'],
            'tin' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'next_of_kin.relationship_type_id' => ['nullable', 'exists:relationship_types,id'],
            'next_of_kin.salutation_id' => ['nullable', 'exists:salutations,id'],
            'next_of_kin.country_id' => ['nullable', 'exists:countries,id'],
            'next_of_kin.first_name' => ['nullable', 'string', 'max:191'],
            'next_of_kin.last_name' => ['nullable', 'string', 'max:191'],
            'next_of_kin.middle_name' => ['nullable', 'string', 'max:191'],
            'next_of_kin.gender' => ['nullable', Rule::in(['female', 'male'])],
            'next_of_kin.phone' => ['nullable', 'string', 'max:60'],
            'next_of_kin.email' => ['nullable', 'email', 'max:191'],
            'next_of_kin.nin_passport_no' => ['nullable', 'string', 'max:191'],
            'next_of_kin.date_of_birth' => ['nullable', 'date'],
            'next_of_kin.address' => ['nullable', 'string', 'max:1000'],
        ]);

        $client = DB::transaction(function () use ($request, $data) {
            $clientData = collect($data)->except('next_of_kin')->toArray();
            $clientData['client_no'] = MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL');
            $clientData['is_prospect'] = $request->boolean('is_prospect');
            $clientData['branch_id'] = $clientData['branch_id'] ?? $request->user()->branch_id;
            $clientData['name'] = $this->clientName($clientData);

            $client = Client::create($clientData);
            $nextOfKin = $data['next_of_kin'] ?? [];

            if (! empty(array_filter($nextOfKin))) {
                $nextOfKin['contact_type'] = 'next_of_kin';
                $nextOfKin['name'] = trim(collect([
                    $nextOfKin['first_name'] ?? null,
                    $nextOfKin['middle_name'] ?? null,
                    $nextOfKin['last_name'] ?? null,
                ])->filter()->implode(' '));

                $client->contacts()->create($nextOfKin);
            }

            return $client;
        });

        return redirect()
            ->route('clients.index')
            ->with('status', $client->display_name.' created.');
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
