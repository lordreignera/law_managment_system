<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\User;
use Illuminate\Http\Request;

class RecoveryController extends Controller
{
    public function index(Request $request)
    {
        $accounts = RecoveryAccount::with(['client', 'assignee'])
            ->forBranchOf($request->user())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('client'), fn ($query) => $query->where('recovery_client_id', $request->integer('client')))
            ->when($request->filled('officer'), fn ($query) => $query->where('assigned_to', $request->integer('officer')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.recoveries.index', [
            'accounts' => $accounts,
            'clients' => RecoveryClient::orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'filters' => $request->only(['search', 'status', 'client', 'officer']),
        ]);
    }

    /**
     * The signed-in officer's own assigned recoveries.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $accounts = RecoveryAccount::with(['client', 'branch'])
            ->where('assigned_to', $user->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $base = RecoveryAccount::where('assigned_to', $user->id);

        $summary = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'outstanding' => (clone $base)->sum('outstanding_amount'),
            'recovered' => (clone $base)->sum('amount_recovered'),
        ];

        return view('modules.recoveries.mine', [
            'accounts' => $accounts,
            'summary' => $summary,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.recoveries.create', [
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAccount($request);

        $data = $this->applyAssignment($data, $request);
        $data['amount_recovered'] = 0;

        $account = RecoveryAccount::create($data);

        return redirect()
            ->route('recoveries.show', $account)
            ->with('status', 'Recovery account created.');
    }

    public function show(Request $request, RecoveryAccount $recovery)
    {
        return view('modules.recoveries.show', [
            'account' => $recovery->load(['client', 'branch', 'assignee', 'assigner', 'activities.user']),
            'activityTypes' => RecoveryActivity::TYPES,
            'canManage' => $request->user()->can('recoveries.update'),
        ]);
    }

    public function edit(RecoveryAccount $recovery)
    {
        return view('modules.recoveries.edit', [
            'account' => $recovery,
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => $this->recoveryOfficers(),
            'statuses' => RecoveryAccount::STATUSES,
        ]);
    }

    public function update(Request $request, RecoveryAccount $recovery)
    {
        $data = $this->validateAccount($request, true);

        $data = $this->applyAssignment($data, $request, $recovery);

        $recovery->update($data);

        return redirect()
            ->route('recoveries.show', $recovery)
            ->with('status', 'Recovery account updated.');
    }

    public function destroy(RecoveryAccount $recovery)
    {
        $recovery->delete();

        return redirect()
            ->route('recoveries.index')
            ->with('status', 'Recovery account removed.');
    }

    /**
     * Users who hold the Recovery Officer role, eligible for assignment.
     */
    private function recoveryOfficers()
    {
        return User::role('Recovery Officer')
            ->with('branch')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);
    }

    /**
     * Stamp the branch + assignment metadata from the chosen officer.
     */
    private function applyAssignment(array $data, Request $request, ?RecoveryAccount $existing = null): array
    {
        $assignedTo = $data['assigned_to'] ?? null;
        $previousAssignee = $existing?->assigned_to;

        if ($assignedTo && $assignedTo != $previousAssignee) {
            $data['assigned_by'] = $request->user()->id;
            $data['assigned_at'] = now();
        }

        // Default the branch from the assigned officer when not explicitly set.
        if (empty($data['branch_id']) && $assignedTo) {
            $data['branch_id'] = User::find($assignedTo)?->branch_id;
        }

        return $data;
    }

    private function validateAccount(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'recovery_client_id' => ['required', 'exists:recovery_clients,id'],
            'debtor_name' => ['required', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'customer_number' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'employer' => ['nullable', 'string', 'max:191'],
            'branch_name' => ['nullable', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:191'],
            'principal_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'bucket' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', array_keys(RecoveryAccount::STATUSES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);
    }
}
