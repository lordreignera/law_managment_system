<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Matter;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with(['category', 'matter', 'recorder'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('expense_category_id'), fn ($query) => $query->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($request->filled('payment_source'), fn ($query) => $query->where('payment_source', $request->string('payment_source')->toString()))
            ->latest('spent_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $scope = Expense::query();

        return view('modules.expenses.index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'expense_category_id', 'payment_source']),
            'categories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->get(),
            'paymentSources' => Expense::PAYMENT_SOURCES,
            'summary' => [
                'Total Entries' => (clone $scope)->count(),
                'Total Expenditure' => (clone $scope)->sum('amount'),
                'This Month' => (clone $scope)->whereMonth('spent_on', now()->month)->whereYear('spent_on', now()->year)->sum('amount'),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.expenses.create', [
            'referenceNumber' => MonthlyReferenceNumber::make(Expense::class, 'reference_no', 'EX'),
            'categories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->get(),
            'matters' => Matter::orderByDesc('id')->limit(200)->get(['id', 'reference_no', 'title']),
            'paymentSources' => Expense::PAYMENT_SOURCES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_source' => ['required', 'in:'.implode(',', array_keys(Expense::PAYMENT_SOURCES))],
            'spent_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'reference_no' => MonthlyReferenceNumber::make(Expense::class, 'reference_no', 'EX'),
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'matter_id' => $data['matter_id'] ?? null,
                'recorded_by' => $request->user()->id,
                'payee' => $data['payee'] ?? null,
                'description' => $data['description'],
                'quantity' => $data['quantity'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'amount' => $data['amount'],
                'payment_source' => $data['payment_source'],
                'spent_on' => $data['spent_on'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($request->hasFile('attachment')) {
                $expense->addAttachment($request->file('attachment'), ['category' => 'expense-receipt']);
            }

            return $expense;
        });

        return redirect()
            ->route('expenses.show', $expense)
            ->with('status', 'Expense '.$expense->reference_no.' recorded.');
    }

    public function show(Expense $expense)
    {
        return view('modules.expenses.show', [
            'expense' => $expense->load(['category', 'matter', 'requisition', 'recorder', 'attachments']),
        ]);
    }
}
