<?php

namespace App\Services;

use App\Models\PayrollComponent;
use App\Models\PayrollEmployeeProfile;
use App\Models\PayrollRule;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PayrollSalaryComponent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Calculates a payroll run from the firm's configured salary components.
 *
 * The calculator deliberately works from a snapshot. Changing a component or
 * salary record after a run has been calculated therefore cannot rewrite the
 * historical payroll result.
 */
class PayrollCalculator
{
    public function calculate(PayrollRun $run): PayrollRun
    {
        $run->loadMissing('period');

        abort_if(! $run->period, 422, 'The payroll run has no period.');
        abort_if(in_array($run->status, ['approved', 'paid', 'voided'], true), 422, 'This payroll run can no longer be recalculated.');

        $period = $run->period;

        return DB::transaction(function () use ($run, $period) {
            $run->lines()->delete();

            $profiles = PayrollEmployeeProfile::query()
                ->with([
                    'user.staffProfile',
                    'salaryRecords' => fn ($query) => $query
                        ->where('status', 'active')
                        ->whereDate('effective_from', '<=', $period->ends_on)
                        ->where(fn ($query) => $query
                            ->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $period->starts_on))
                        ->with(['components.component']),
                ])
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $period->ends_on))
                ->where(fn ($query) => $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $period->starts_on))
                ->whereHas('user.staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->orderBy('id')
                ->get();
            $rules = PayrollRule::query()
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $period->ends_on)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->starts_on))
                ->orderBy('sort_order')->orderBy('id')->get();

            $totals = [
                'employees' => 0,
                'gross_pay' => 0.0,
                'taxable_pay' => 0.0,
                'employee_deductions' => 0.0,
                'employer_contributions' => 0.0,
                'net_pay' => 0.0,
            ];

            foreach ($profiles as $profile) {
                $salary = $profile->salaryRecords->sortByDesc('effective_from')->first();

                if (! $salary || ! $profile->user) {
                    continue;
                }

                $line = $this->calculateEmployee($run, $profile, $salary, $rules);
                $totals['employees']++;
                foreach (array_keys($totals) as $key) {
                    if ($key !== 'employees') {
                        $totals[$key] += (float) $line->{$key};
                    }
                }
            }

            $run->forceFill([
                'status' => 'calculated',
                'calculated_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'totals' => $this->roundedTotals($totals),
            ])->save();

            $period->update(['status' => 'processing']);

            return $run->fresh(['period', 'lines.components']);
        });
    }

    protected function calculateEmployee(PayrollRun $run, PayrollEmployeeProfile $profile, $salary, $rules): PayrollRunLine
    {
        $baseSalary = round((float) $salary->base_salary, 2);
        $context = [
            'base_salary' => $baseSalary,
            'gross_pay' => $baseSalary,
            'taxable_pay' => $baseSalary,
            'total_deductions' => 0.0,
            'net_pay' => $baseSalary,
        ];
        $components = [];

        $components[] = [
            'model' => null,
            'code' => 'BASIC-SALARY',
            'name' => 'Basic salary',
            'component_type' => 'earning',
            'amount' => $baseSalary,
            'rate' => null,
            'calculation_method' => 'fixed',
            'is_taxable' => true,
            'is_employer_cost' => false,
            'metadata' => ['source' => 'salary_record'],
        ];

        $assignments = $salary->components
            ->filter(fn (PayrollSalaryComponent $assignment) => $assignment->is_active && $assignment->component?->is_active)
            ->sortBy(fn (PayrollSalaryComponent $assignment) => [$assignment->component->sort_order, $assignment->component->id]);

        foreach ($assignments as $assignment) {
            $component = $assignment->component;
            if (! $component) {
                continue;
            }

            if ($component->component_type === 'earning' && ! $component->is_employer_cost) {
                $components[] = $this->componentSnapshot($assignment, $component, $context);
                $context['gross_pay'] += end($components)['amount'];
                if ($component->is_taxable) {
                    $context['taxable_pay'] += end($components)['amount'];
                }
                $context['net_pay'] = $context['gross_pay'] - $context['total_deductions'];
            }
        }

        foreach ($assignments as $assignment) {
            $component = $assignment->component;
            if (! $component || ($component->component_type === 'earning' && ! $component->is_employer_cost)) {
                continue;
            }

            if ($component->component_type === 'employer_contribution' || $component->is_employer_cost) {
                continue;
            }

            $snapshot = $this->componentSnapshot($assignment, $component, $context);
            $components[] = $snapshot;
            $context['total_deductions'] += $snapshot['amount'];
            $context['net_pay'] = $context['gross_pay'] - $context['total_deductions'];
        }

        foreach ($rules as $rule) {
            $snapshot = $this->ruleSnapshot($rule, $context);
            if ($snapshot['amount'] <= 0) {
                continue;
            }
            $components[] = $snapshot;
            if (! $snapshot['is_employer_cost']) {
                $context['total_deductions'] += $snapshot['amount'];
                $context['net_pay'] = $context['gross_pay'] - $context['total_deductions'];
            }
        }

        foreach ($assignments as $assignment) {
            $component = $assignment->component;
            if (! $component || ! ($component->component_type === 'employer_contribution' || $component->is_employer_cost)) {
                continue;
            }

            $components[] = $this->componentSnapshot($assignment, $component, $context);
        }

        $line = $run->lines()->create([
            'user_id' => $profile->user_id,
            'payroll_employee_profile_id' => $profile->id,
            'employee_name' => $profile->user->name,
            'staff_no' => $profile->user->staffProfile?->staff_no,
            'base_salary' => $baseSalary,
            'gross_pay' => round($context['gross_pay'], 2),
            'taxable_pay' => round($context['taxable_pay'], 2),
            'employee_deductions' => round($context['total_deductions'], 2),
            'employer_contributions' => round(collect($components)->where('is_employer_cost', true)->sum('amount'), 2),
            'net_pay' => round(max(0, $context['net_pay']), 2),
            'snapshot' => [
                'period' => [$run->period->starts_on?->toDateString(), $run->period->ends_on?->toDateString()],
                'salary_record_id' => $salary->id,
                'currency_type_id' => $salary->currency_type_id,
            ],
        ]);

        $line->payslip()->create([
            'payslip_number' => 'PS-'.$run->period->code.'-'.str_pad((string) $line->id, 5, '0', STR_PAD_LEFT),
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        foreach ($components as $component) {
            $line->components()->create([
                'payroll_component_id' => $component['model']?->id,
                'code' => $component['code'],
                'name' => $component['name'],
                'component_type' => $component['component_type'],
                'amount' => round($component['amount'], 2),
                'rate' => $component['rate'],
                'calculation_method' => $component['calculation_method'],
                'is_taxable' => $component['is_taxable'],
                'is_employer_cost' => $component['is_employer_cost'],
                'metadata' => $component['metadata'],
            ]);
        }

        return $line;
    }

    protected function componentSnapshot(PayrollSalaryComponent $assignment, PayrollComponent $component, array $context): array
    {
        $method = $component->calculation_method;
        $rate = $assignment->rate ?? $component->rate;
        $formula = filled($assignment->formula) ? $assignment->formula : $component->formula;
        $basis = $context[$component->calculation_basis] ?? $context['gross_pay'];

        $amount = match ($method) {
            'percentage' => ($basis * ((float) ($rate ?? 0))) / 100,
            'formula' => $this->evaluateFormula($formula, $context),
            default => (float) ($assignment->amount ?? $component->amount ?? 0),
        };

        return [
            'model' => $component,
            'code' => $component->code,
            'name' => $component->name,
            'component_type' => $component->component_type,
            'amount' => round(max(0, $amount), 2),
            'rate' => $rate,
            'calculation_method' => $method,
            'is_taxable' => (bool) $component->is_taxable,
            'is_employer_cost' => (bool) ($component->is_employer_cost || $component->component_type === 'employer_contribution'),
            'metadata' => [
                'basis' => $component->calculation_basis,
                'formula' => $formula,
            ],
        ];
    }

    protected function ruleSnapshot(PayrollRule $rule, array $context): array
    {
        $configuration = $rule->configuration ?: [];
        $basisName = (string) ($configuration['basis'] ?? ($rule->rule_type === 'paye' ? 'taxable_pay' : 'gross_pay'));
        $basis = (float) ($context[$basisName] ?? $context['gross_pay']);
        $amount = match ($rule->calculation_method) {
            'percentage' => ($basis * ((float) ($rule->rate ?? 0))) / 100,
            'fixed' => (float) ($configuration['amount'] ?? 0),
            'bands' => $this->evaluateBands($basis, $configuration['bands'] ?? []),
            'formula' => $this->evaluateFormula($rule->formula, $context),
            default => 0.0,
        };
        $employerCost = (bool) ($rule->is_employer_cost || ($configuration['employer'] ?? false));

        return [
            'model' => null,
            'code' => $rule->code,
            'name' => $rule->name,
            'component_type' => $employerCost ? 'employer_contribution' : ($rule->rule_type === 'paye' ? 'tax' : 'deduction'),
            'amount' => round(max(0, $amount), 2),
            'rate' => $rule->rate,
            'calculation_method' => $rule->calculation_method,
            'is_taxable' => false,
            'is_employer_cost' => $employerCost,
            'metadata' => ['source' => 'payroll_rule', 'basis' => $basisName, 'rule_type' => $rule->rule_type],
        ];
    }

    protected function evaluateBands(float $basis, array $bands): float
    {
        $previous = 0.0;
        $total = 0.0;
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $upper = array_key_exists('up_to', $band) && $band['up_to'] !== null ? (float) $band['up_to'] : null;
            $rate = (float) ($band['rate'] ?? 0);
            $portion = $upper === null
                ? max(0, $basis - $previous)
                : max(0, min($basis, $upper) - $previous);
            $total += ($portion * $rate) / 100;
            if ($upper !== null) {
                $previous = $upper;
            }
            if ($upper !== null && $basis <= $upper) {
                break;
            }
        }

        return $total;
    }

    /**
     * Evaluate the small arithmetic language used for configured formulas.
     * Only numbers, approved payroll variables, operators and parentheses are
     * accepted; arbitrary PHP code can never be executed from a form value.
     */
    protected function evaluateFormula(?string $formula, array $context): float
    {
        if (! filled($formula)) {
            return 0.0;
        }

        preg_match_all('/\s*(?:(\d+(?:\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)|([+\-*\/()]))/', $formula, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $tokens = [];
        $cursor = 0;
        foreach ($matches as $match) {
            if (trim(substr($formula, $cursor, $match[0][1] - $cursor)) !== '') {
                throw new InvalidArgumentException('The payroll formula contains an unsupported token.');
            }
            $tokens[] = $match[1][0] !== '' ? ['number', (float) $match[1][0]] : ($match[2][0] !== '' ? ['variable', $match[2][0]] : ['operator', $match[3][0]]);
            $cursor = $match[0][1] + strlen($match[0][0]);
        }
        if (trim(substr($formula, $cursor)) !== '' || $tokens === []) {
            throw new InvalidArgumentException('The payroll formula contains an unsupported token.');
        }

        $position = 0;
        $parseExpression = null;
        $parseTerm = null;
        $parseFactor = null;
        $parseExpression = function () use (&$parseExpression, &$parseTerm, &$position, $tokens): float {
            $value = $parseTerm();
            while ($position < count($tokens) && in_array($tokens[$position][1], ['+', '-'], true)) {
                $operator = $tokens[$position++][1];
                $right = $parseTerm();
                $value = $operator === '+' ? $value + $right : $value - $right;
            }

            return $value;
        };
        $parseTerm = function () use (&$parseFactor, &$position, $tokens): float {
            $value = $parseFactor();
            while ($position < count($tokens) && in_array($tokens[$position][1], ['*', '/'], true)) {
                $operator = $tokens[$position++][1];
                $right = $parseFactor();
                if ($operator === '/' && abs($right) < 0.0000001) {
                    throw new InvalidArgumentException('A payroll formula cannot divide by zero.');
                }
                $value = $operator === '*' ? $value * $right : $value / $right;
            }

            return $value;
        };
        $parseFactor = function () use (&$parseFactor, &$parseExpression, &$position, $tokens, $context): float {
            if ($position >= count($tokens)) {
                throw new InvalidArgumentException('The payroll formula is incomplete.');
            }
            [$type, $token] = $tokens[$position++];
            if ($type === 'number') {
                return $token;
            }
            if ($type === 'variable') {
                if (! array_key_exists($token, $context)) {
                    throw new InvalidArgumentException("Unknown payroll formula variable: {$token}.");
                }

                return (float) $context[$token];
            }
            if ($token === '+' || $token === '-') {
                $value = $parseFactor();

                return $token === '-' ? -$value : $value;
            }
            if ($token === '(') {
                $value = $parseExpression();
                if ($position >= count($tokens) || $tokens[$position][1] !== ')') {
                    throw new InvalidArgumentException('The payroll formula has unbalanced parentheses.');
                }
                $position++;

                return $value;
            }
            throw new InvalidArgumentException('The payroll formula contains an unexpected operator.');
        };

        // The closures are mutually recursive, so they are invoked only after
        // all references have been initialised.
        $value = $parseExpression();
        if ($position !== count($tokens)) {
            throw new InvalidArgumentException('The payroll formula contains an unexpected token.');
        }

        return round($value, 2);
    }

    protected function roundedTotals(array $totals): array
    {
        foreach ($totals as $key => $value) {
            if ($key !== 'employees') {
                $totals[$key] = round($value, 2);
            }
        }

        return $totals;
    }
}
