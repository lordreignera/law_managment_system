<?php

namespace App\Http\Controllers;

use App\Models\PayrollComponent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $components = PayrollComponent::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->when($request->filled('component_type'), fn ($query) => $query->where('component_type', $request->string('component_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('modules.payroll.components.index', [
            'components' => $components,
            'types' => PayrollComponent::TYPES,
            'methods' => PayrollComponent::METHODS,
            'filters' => $request->only(['search', 'component_type', 'status']),
            'summary' => [
                'Active' => PayrollComponent::where('is_active', true)->count(),
                'Earnings' => PayrollComponent::where('component_type', 'earning')->count(),
                'Deductions' => PayrollComponent::whereIn('component_type', ['deduction', 'tax'])->count(),
                'Employer Costs' => PayrollComponent::where('is_employer_cost', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.payroll.components.create', [
            'component' => new PayrollComponent([
                'code' => PayrollComponent::nextCode(),
                'component_type' => 'earning',
                'calculation_method' => 'fixed',
                'calculation_basis' => 'basic_salary',
                'is_active' => true,
                'is_recurring' => true,
            ]),
            'types' => PayrollComponent::TYPES,
            'methods' => PayrollComponent::METHODS,
            'bases' => PayrollComponent::BASES,
        ]);
    }

    public function store(Request $request)
    {
        $component = PayrollComponent::create($this->validatedData($request));

        return redirect()->route('payroll.components.index')->with('status', $component->name.' component created.');
    }

    public function edit(PayrollComponent $component)
    {
        return view('modules.payroll.components.edit', [
            'component' => $component,
            'types' => PayrollComponent::TYPES,
            'methods' => PayrollComponent::METHODS,
            'bases' => PayrollComponent::BASES,
        ]);
    }

    public function update(Request $request, PayrollComponent $component)
    {
        $component->update($this->validatedData($request, $component));

        return redirect()->route('payroll.components.index')->with('status', $component->name.' component updated.');
    }

    public function destroy(PayrollComponent $component)
    {
        if ($component->salaryComponents()->exists()) {
            return back()->withErrors(['component' => 'This component is assigned to a salary record. Deactivate it instead of deleting it.']);
        }

        $component->delete();

        return redirect()->route('payroll.components.index')->with('status', 'Payroll component deleted.');
    }

    private function validatedData(Request $request, ?PayrollComponent $component = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('payroll_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:191'],
            'component_type' => ['required', Rule::in(array_keys(PayrollComponent::TYPES))],
            'calculation_method' => ['required', Rule::in(array_keys(PayrollComponent::METHODS))],
            'calculation_basis' => ['required', Rule::in(array_keys(PayrollComponent::BASES))],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formula' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_nssf_liable' => ['nullable', 'boolean'],
            'is_employer_cost' => ['nullable', 'boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['amount'] = $data['amount'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        foreach (['is_taxable', 'is_nssf_liable', 'is_employer_cost', 'is_recurring', 'is_active'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        return $data;
    }
}
