<?php

namespace App\Http\Controllers;

use App\Models\AdrResolution;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientAdrController extends Controller
{
    public function create(Client $client)
    {
        $sourceIntake = $this->sourceIntake($client);

        return view('modules.clients.adr', [
            'client' => $client,
            'adrNumber' => MonthlyReferenceNumber::make(AdrResolution::class, 'adr_no', 'ADR'),
            'sourceIntake' => $sourceIntake,
            'conflictParties' => $sourceIntake?->conflictParties ?? collect(),
            'suggestedTitle' => $sourceIntake?->legal_issue,
        ]);
    }

    public function store(Request $request, Client $client)
    {
        $sourceIntake = $this->sourceIntake($client);
        $hasConflictParties = $sourceIntake?->conflictParties->isNotEmpty() ?? false;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'intake_conflict_party_id' => [$hasConflictParties ? 'required' : 'nullable', 'integer'],
            'conflict_party_name' => [$hasConflictParties ? 'nullable' : 'required', 'string', 'max:191'],
            'conflict_party_contact' => ['nullable', 'string', 'max:191'],
            'method' => ['nullable', Rule::in(['call', 'email', 'letter', 'meeting', 'mediation', 'other'])],
            'resolved_on' => ['nullable', 'date'],
            'response' => ['required', Rule::in(['pending', 'accepted_negotiation', 'declined', 'no_response', 'settled', 'court_required', 'other'])],
            'response_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $selectedConflictParty = null;

        if (! empty($data['intake_conflict_party_id'])) {
            $selectedConflictParty = $sourceIntake?->conflictParties->firstWhere('id', (int) $data['intake_conflict_party_id']);

            if (! $selectedConflictParty) {
                throw ValidationException::withMessages([
                    'intake_conflict_party_id' => 'Select a conflict party from this client intake.',
                ]);
            }

            $data['conflict_party_name'] = $selectedConflictParty->name;
            $data['conflict_party_contact'] = $selectedConflictParty->contact;
        }

        $adr = AdrResolution::create($data + [
            'client_id' => $client->id,
            'intake_conflict_party_id' => $selectedConflictParty?->id,
            'created_by' => auth()->id(),
            'adr_no' => MonthlyReferenceNumber::make(AdrResolution::class, 'adr_no', 'ADR'),
            'status' => $data['response'] === 'settled' ? 'settled' : 'open',
        ]);

        return redirect()
            ->route('clients.adr.show', $adr)
            ->with('status', 'ADR resolution recorded for '.$client->display_name.'.');
    }

    public function show(AdrResolution $adr)
    {
        return view('modules.adr.show', [
            'adr' => $adr->load(['client', 'intakeConflictParty', 'file.billingType', 'file.matter']),
        ]);
    }

    private function sourceIntake(Client $client): ?ClientIntake
    {
        return $client->intakes()
            ->with('conflictParties')
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->latest()
            ->first();
    }
}
