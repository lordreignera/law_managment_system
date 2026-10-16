<?php

namespace App\Http\Controllers;

use App\Models\PayrollRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollRuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = PayrollRule::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('rule_type'), fn ($query) => $query->where('rule_type', $request->string('rule_type')->toString()))
            ->orderBy('sort_order')->orderBy('effective_from', 'desc')->paginate(20)->withQueryString();

        return view('modules.payroll.rules.index', [
            'rules' => $rules,
            'types' => PayrollRule::TYPES,
            'methods' => PayrollRule::METHODS,
            'filters' => $request->only(['search', 'rule_type']),
        ]);
    }

    public function create()
    {
        return view('modules.payroll.rules.create', [
            'rule' => new PayrollRule(['code' => PayrollRule::nextCode(), 'effective_from' => today(), 'is_active' => true]),
            'types' => PayrollRule::TYPES,
            'methods' => PayrollRule::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $rule = PayrollRule::create($this->validatedData($request));

        return redirect()->route('payroll.rules.index')->with('status', $rule->name.' rule created.');
    }

    public function edit(PayrollRule $rule)
    {
        return view('modules.payroll.rules.edit', [
            'rule' => $rule,
            'types' => PayrollRule::TYPES,
            'methods' => PayrollRule::METHODS,
        ]);
    }

    public function update(Request $request, PayrollRule $rule)
    {
        $rule->update($this->validatedData($request, $rule));

        return redirect()->route('payroll.rules.index')->with('status', $rule->name.' rule updated.');
    }

    public function destroy(PayrollRule $rule)
    {
        $rule->delete();

        return redirect()->route('payroll.rules.index')->with('status', 'Payroll rule deleted.');
    }

    private function validatedData(Request $request, ?PayrollRule $rule = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('payroll_rules', 'code')->ignore($rule?->id)],
            'name' => ['required', 'string', 'max:191'],
            'rule_type' => ['required', Rule::in(array_keys(PayrollRule::TYPES))],
            'calculation_method' => ['required', Rule::in(array_keys(PayrollRule::METHODS))],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formula' => ['nullable', 'string', 'max:5000'],
            'configuration' => ['nullable', 'json'],
            'is_employer_cost' => ['nullable', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['configuration'] = filled($data['configuration'] ?? null) ? json_decode($data['configuration'], true, 512, JSON_THROW_ON_ERROR) : null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['is_employer_cost'] = $request->boolean('is_employer_cost');

        return $data;
    }
}
