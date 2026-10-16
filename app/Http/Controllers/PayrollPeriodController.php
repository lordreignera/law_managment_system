<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollPeriodController extends Controller
{
    public function index(Request $request)
    {
        $periods = PayrollPeriod::with(['creator', 'run'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('starts_on')
            ->paginate(20)
            ->withQueryString();

        return view('modules.payroll.periods.index', [
            'periods' => $periods,
            'statuses' => PayrollPeriod::STATUSES,
            'filters' => $request->only(['search', 'status']),
            'summary' => [
                'Open' => PayrollPeriod::whereNotIn('status', ['closed', 'cancelled'])->count(),
                'Awaiting Approval' => PayrollPeriod::where('status', 'awaiting_approval')->count(),
                'Approved' => PayrollPeriod::where('status', 'approved')->count(),
                'Closed' => PayrollPeriod::where('status', 'closed')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.payroll.periods.create', [
            'period' => new PayrollPeriod([
                'code' => PayrollPeriod::nextCode(),
                'starts_on' => now()->startOfMonth(),
                'ends_on' => now()->endOfMonth(),
                'payment_on' => now()->endOfMonth(),
                'status' => 'draft',
            ]),
            'statuses' => PayrollPeriod::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $period = PayrollPeriod::create($data + ['created_by' => $request->user()->id, 'status' => 'draft']);

        return redirect()->route('payroll.periods.show', $period)->with('status', 'Payroll period created.');
    }

    public function show(PayrollPeriod $period)
    {
        $period->load(['creator', 'run.preparer', 'run.lines.components', 'run.approvals.approver']);

        return view('modules.payroll.periods.show', [
            'period' => $period,
        ]);
    }

    public function createRun(Request $request, PayrollPeriod $period)
    {
        abort_if($period->run, 422, 'This payroll period already has a run.');
        abort_if(in_array($period->status, ['closed', 'cancelled'], true), 422, 'Closed or cancelled periods cannot be processed.');

        DB::transaction(function () use ($request, $period) {
            PayrollRun::create([
                'payroll_period_id' => $period->id,
                'status' => 'draft',
                'prepared_by' => $request->user()->id,
            ]);
            $period->update(['status' => 'processing']);
        });

        return redirect()->route('payroll.periods.show', $period)->with('status', 'Payroll run opened for preparation.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('payroll_periods', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'payment_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
