<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\CurrencyType;
use App\Models\PayrollComponent;
use App\Models\PayrollEmployeeProfile;
use App\Models\PayrollSalaryRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $profiles = PayrollEmployeeProfile::with(['user.staffProfile', 'user.branch', 'user.department', 'currentSalary'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('tin', 'like', "%{$search}%")
                        ->orWhere('nssf_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('modules.payroll.employees.index', [
            'profiles' => $profiles,
            'filters' => $request->only(['search', 'status']),
            'summary' => [
                'Active Payroll Staff' => PayrollEmployeeProfile::where('is_active', true)->count(),
                'Missing TIN' => PayrollEmployeeProfile::where('is_active', true)->whereNull('tin')->count(),
                'Missing NSSF No.' => PayrollEmployeeProfile::where('is_active', true)->whereNull('nssf_number')->count(),
                'Missing Salary' => PayrollEmployeeProfile::where('is_active', true)->whereDoesntHave('currentSalary')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.payroll.employees.create', $this->formData([
            'profile' => new PayrollEmployeeProfile(['is_active' => true, 'tax_residency' => 'resident', 'payment_method' => 'bank']),
            'salary' => new PayrollSalaryRecord(['effective_from' => today(), 'pay_frequency' => 'monthly', 'status' => 'active']),
            'employee' => null,
            'assignedComponents' => collect(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request) {
            $profile = PayrollEmployeeProfile::create($data['profile']);
            $salary = $profile->salaryRecords()->create($data['salary']);
            $this->syncComponents($salary, $request);
        });

        return redirect()->route('payroll.employees.index')->with('status', 'Payroll employee profile created.');
    }

    public function show(PayrollEmployeeProfile $profile)
    {
        $profile->load(['user.staffProfile', 'user.branch', 'user.department', 'bank', 'bankBranch', 'salaryRecords.components.component']);

        return view('modules.payroll.employees.show', compact('profile'));
    }

    public function edit(PayrollEmployeeProfile $profile)
    {
        $profile->load(['user', 'currentSalary.components.component']);

        return view('modules.payroll.employees.edit', $this->formData([
            'profile' => $profile,
            'salary' => $profile->currentSalary ?: new PayrollSalaryRecord(['effective_from' => today(), 'pay_frequency' => 'monthly', 'status' => 'active']),
            'employee' => $profile->user,
            'assignedComponents' => $profile->currentSalary?->components?->keyBy('payroll_component_id') ?: collect(),
        ]));
    }

    public function update(Request $request, PayrollEmployeeProfile $profile)
    {
        $data = $this->validatedData($request, $profile);

        DB::transaction(function () use ($data, $request, $profile) {
            $profile->update($data['profile']);
            $salary = $profile->currentSalary ?: $profile->salaryRecords()->create($data['salary']);
            $salary->update($data['salary']);
            $this->syncComponents($salary, $request);
        });

        return redirect()->route('payroll.employees.show', $profile)->with('status', 'Payroll employee profile updated.');
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'employees' => User::with(['staffProfile', 'branch', 'department'])
                ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->whereDoesntHave('payrollProfile')
                ->orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'bankBranches' => BankBranch::with('bank')->orderBy('name')->get(),
            'currencies' => CurrencyType::where('is_active', true)->orderBy('name')->get(),
            'components' => PayrollComponent::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'residencies' => PayrollEmployeeProfile::TAX_RESIDENCIES,
            'paymentMethods' => PayrollEmployeeProfile::PAYMENT_METHODS,
            'frequencies' => PayrollSalaryRecord::FREQUENCIES,
        ], $overrides);
    }

    private function validatedData(Request $request, ?PayrollEmployeeProfile $profile = null): array
    {
        $data = $request->validate([
            'user_id' => [$profile ? 'nullable' : 'required', 'exists:users,id', Rule::unique('payroll_employee_profiles', 'user_id')->ignore($profile?->id)],
            'tin' => ['nullable', 'string', 'max:80'],
            'nssf_number' => ['nullable', 'string', 'max:80'],
            'tax_residency' => ['required', Rule::in(array_keys(PayrollEmployeeProfile::TAX_RESIDENCIES))],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', 'exists:bank_branches,id'],
            'payment_method' => ['required', Rule::in(array_keys(PayrollEmployeeProfile::PAYMENT_METHODS))],
            'account_name' => ['nullable', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'currency_type_id' => ['nullable', 'exists:currency_types,id'],
            'pay_frequency' => ['required', Rule::in(array_keys(PayrollSalaryRecord::FREQUENCIES))],
            'salary_effective_from' => ['required', 'date'],
            'salary_effective_to' => ['nullable', 'date', 'after_or_equal:salary_effective_from'],
            'salary_notes' => ['nullable', 'string', 'max:3000'],
            'component_ids' => ['nullable', 'array'],
            'component_ids.*' => ['integer', 'exists:payroll_components,id'],
            'component_amounts' => ['nullable', 'array'],
            'component_rates' => ['nullable', 'array'],
        ]);

        return [
            'profile' => collect($data)->only([
                'user_id', 'tin', 'nssf_number', 'tax_residency', 'bank_id', 'bank_branch_id', 'payment_method',
                'account_name', 'account_number', 'effective_from', 'effective_to', 'notes',
            ])->all() + ['is_active' => $request->boolean('is_active')],
            'salary' => [
                'base_salary' => $data['base_salary'],
                'currency_type_id' => $data['currency_type_id'] ?? null,
                'pay_frequency' => $data['pay_frequency'],
                'effective_from' => $data['salary_effective_from'],
                'effective_to' => $data['salary_effective_to'] ?? null,
                'status' => 'active',
                'notes' => $data['salary_notes'] ?? null,
            ],
        ];
    }

    private function syncComponents(PayrollSalaryRecord $salary, Request $request): void
    {
        $ids = collect($request->input('component_ids', []))->map(fn ($id) => (int) $id)->unique()->values();
        $salary->components()->whereNotIn('payroll_component_id', $ids)->delete();

        foreach ($ids as $id) {
            $salary->components()->updateOrCreate(
                ['payroll_component_id' => $id],
                [
                    'amount' => $request->input("component_amounts.$id") !== null ? (float) $request->input("component_amounts.$id") : null,
                    'rate' => $request->input("component_rates.$id") !== null ? (float) $request->input("component_rates.$id") : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
