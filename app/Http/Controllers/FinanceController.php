<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ChartAccount;
use App\Models\Invoice;
use App\Models\PettyCashTransaction;
use App\Models\Requisition;

class FinanceController extends Controller
{
    public function index()
    {
        return view('modules.finance.index', [
            'invoices' => Invoice::latest()->limit(10)->get(),
            'requisitions' => Requisition::latest()->limit(10)->get(),
        ]);
    }

    public function dashboard()
    {
        $invoiceTotal = (float) Invoice::sum('total');
        $invoicePaid = (float) Invoice::sum('paid_amount');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $expensesThisMonth = (float) Expense::whereBetween('spent_on', [$monthStart, $monthEnd])->sum('amount');

        return view('modules.finance.dashboard', [
            'stats' => [
                'Total Invoiced' => number_format($invoiceTotal),
                'Collected' => number_format($invoicePaid),
                'Outstanding' => number_format(max($invoiceTotal - $invoicePaid, 0)),
                'Petty Cash Balance' => number_format(PettyCashTransaction::balance()),
                'Expenses (This Month)' => number_format($expensesThisMonth),
                'Pending Requisitions' => number_format(Requisition::where('status', 'submitted')->count()),
                'Chart Accounts' => number_format(ChartAccount::count()),
            ],
            'flow' => [
                [
                    'stage' => 'Requisitions Submitted',
                    'description' => 'Spending requests awaiting approval before funds are released.',
                    'count' => Requisition::where('status', 'submitted')->count(),
                    'route' => route('requisitions.index'),
                ],
                [
                    'stage' => 'Approved Requisitions',
                    'description' => 'Approved requests ready to be paid or disbursed.',
                    'count' => Requisition::where('status', 'approved')->count(),
                    'route' => route('requisitions.index', ['status' => 'approved']),
                ],
                [
                    'stage' => 'Expenses Recorded',
                    'description' => 'Direct expenses and petty cash disbursements captured this month.',
                    'count' => Expense::whereBetween('spent_on', [$monthStart, $monthEnd])->count(),
                    'route' => route('expenses.index'),
                ],
                [
                    'stage' => 'Invoices Issued',
                    'description' => 'Client invoices raised and sent for payment.',
                    'count' => Invoice::where('status', '!=', 'draft')->count(),
                    'route' => route('finance.index'),
                ],
                [
                    'stage' => 'Payments Received',
                    'description' => 'Invoices with part or full payment recorded.',
                    'count' => Invoice::where('paid_amount', '>', 0)->count(),
                    'route' => route('finance.index'),
                ],
            ],
            'recentInvoices' => Invoice::with('client')->latest('invoice_date')->limit(8)->get(),
            'recentExpenses' => Expense::with(['category', 'recorder'])->latest('spent_on')->limit(8)->get(),
            'pendingRequisitions' => Requisition::with(['requester', 'category'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
