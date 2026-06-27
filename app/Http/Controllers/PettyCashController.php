<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\PettyCashTransaction;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;

class PettyCashController extends Controller
{
    public function index(Request $request)
    {
        $transactions = PettyCashTransaction::with(['category', 'recorder'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->latest('transacted_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.petty-cash.index', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'type']),
            'types' => PettyCashTransaction::TYPES,
            'summary' => [
                'Total Top-ups' => PettyCashTransaction::where('type', 'top_up')->sum('amount'),
                'Total Disbursed' => PettyCashTransaction::where('type', 'disbursement')->sum('amount'),
                'Current Balance' => PettyCashTransaction::balance(),
            ],
        ]);
    }

    public function create()
    {
        return view('modules.petty-cash.create', [
            'referenceNumber' => MonthlyReferenceNumber::make(PettyCashTransaction::class, 'reference_no', 'PC'),
            'categories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->get(),
            'types' => PettyCashTransaction::TYPES,
            'balance' => PettyCashTransaction::balance(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(PettyCashTransaction::TYPES))],
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transacted_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['type'] === 'disbursement' && $data['amount'] > PettyCashTransaction::balance()) {
            return back()
                ->withInput()
                ->withErrors(['amount' => 'Disbursement exceeds the available petty cash balance.']);
        }

        $transaction = PettyCashTransaction::create([
            'reference_no' => MonthlyReferenceNumber::make(PettyCashTransaction::class, 'reference_no', 'PC'),
            'type' => $data['type'],
            'expense_category_id' => $data['type'] === 'disbursement' ? ($data['expense_category_id'] ?? null) : null,
            'recorded_by' => $request->user()->id,
            'payee' => $data['payee'] ?? null,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'transacted_on' => $data['transacted_on'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('petty-cash.index')
            ->with('status', 'Petty cash '.$transaction->typeLabel().' '.$transaction->reference_no.' recorded.');
    }
}
