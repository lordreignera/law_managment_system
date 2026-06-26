<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ContactPosition;
use App\Models\Country;
use App\Models\RelationshipType;
use App\Models\Salutation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
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

    public function create()
    {
        return view('modules.clients.create', [
            'salutations' => Salutation::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'positions' => ContactPosition::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'countries' => Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'relationships' => RelationshipType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
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
            $clientData['is_prospect'] = $request->boolean('is_prospect');
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
