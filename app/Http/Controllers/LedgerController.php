<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PettyCashTransaction;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $expenseQuery = Expense::query()
            ->when($from, fn ($query) => $query->whereDate('spent_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('spent_on', '<=', $to));

        $pettyQuery = PettyCashTransaction::query()
            ->where('type', 'disbursement')
            ->when($from, fn ($query) => $query->whereDate('transacted_on', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transacted_on', '<=', $to));

        // Expenditure grouped by category, combining direct expenses and petty cash disbursements.
        $expenseByCategory = (clone $expenseQuery)
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $pettyByCategory = (clone $pettyQuery)
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $categories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get();

        $ledgerRows = $categories->map(function ($category) use ($expenseByCategory, $pettyByCategory) {
            $expenseTotal = (float) ($expenseByCategory[$category->id] ?? 0);
            $pettyTotal = (float) ($pettyByCategory[$category->id] ?? 0);

            return [
                'category' => $category->name,
                'expenses' => $expenseTotal,
                'petty_cash' => $pettyTotal,
                'total' => $expenseTotal + $pettyTotal,
            ];
        })->push([
            'category' => 'Uncategorised',
            'expenses' => (float) ($expenseByCategory[null] ?? 0),
            'petty_cash' => (float) ($pettyByCategory[null] ?? 0),
            'total' => (float) ($expenseByCategory[null] ?? 0) + (float) ($pettyByCategory[null] ?? 0),
        ])->filter(fn ($row) => $row['total'] > 0)->values();

        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        $pettyTotal = (float) (clone $pettyQuery)->sum('amount');

        return view('modules.ledger.index', [
            'filters' => ['from' => $request->input('from'), 'to' => $request->input('to')],
            'ledgerRows' => $ledgerRows,
            'summary' => [
                'Direct Expenses' => $expenseTotal,
                'Petty Cash Spent' => $pettyTotal,
                'Total Expenditure' => $expenseTotal + $pettyTotal,
                'Petty Cash Balance' => PettyCashTransaction::balance(),
            ],
        ]);
    }
}
