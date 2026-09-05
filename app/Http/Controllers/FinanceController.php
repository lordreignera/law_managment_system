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

        $submittedRequisitions = Requisition::where('status', 'submitted')->count();
        $approvedRequisitions = Requisition::where('status', 'approved')->count();
        $expensesCountThisMonth = Expense::whereBetween('spent_on', [$monthStart, $monthEnd])->count();
        $issuedInvoices = Invoice::where('status', '!=', 'draft')->count();
        $paidInvoices = Invoice::where('paid_amount', '>', 0)->count();

        return view('modules.finance.dashboard', [
            'stats' => [
                [
                    'label' => 'Total Invoiced',
                    'value' => number_format($invoiceTotal),
                    'icon' => 'mdi-file-document-outline',
                    'route' => route('finance.index'),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Collected',
                    'value' => number_format($invoicePaid),
                    'icon' => 'mdi-cash-check',
                    'route' => route('finance.index'),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Outstanding',
                    'value' => number_format(max($invoiceTotal - $invoicePaid, 0)),
                    'icon' => 'mdi-receipt-text-clock-outline',
                    'route' => route('finance.index'),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Petty Cash Balance',
                    'value' => number_format(PettyCashTransaction::balance()),
                    'icon' => 'mdi-wallet-outline',
                    'route' => route('petty-cash.index'),
                    'permission' => 'petty-cash.index',
                ],
                [
                    'label' => 'Expenses This Month',
                    'value' => number_format($expensesThisMonth),
                    'icon' => 'mdi-cash-minus',
                    'route' => route('expenses.index'),
                    'permission' => 'expenses.index',
                ],
                [
                    'label' => 'Pending Requisitions',
                    'value' => number_format($submittedRequisitions),
                    'icon' => 'mdi-clipboard-clock-outline',
                    'route' => route('requisitions.index', ['status' => 'submitted']),
                    'permission' => 'requisitions.index',
                ],
                [
                    'label' => 'Chart Accounts',
                    'value' => number_format(ChartAccount::count()),
                    'icon' => 'mdi-format-list-numbered',
                    'route' => route('finance.chart-accounts.index'),
                    'permission' => 'finance.chart-accounts.index',
                ],
            ],
            'flow' => [
                [
                    'stage' => 'Requisitions Submitted',
                    'description' => 'Spending requests awaiting approval before funds are released.',
                    'count' => $submittedRequisitions,
                    'route' => route('requisitions.index', ['status' => 'submitted']),
                    'permission' => 'requisitions.index',
                    'icon' => 'mdi-clipboard-text-clock-outline',
                ],
                [
                    'stage' => 'Approved Requisitions',
                    'description' => 'Approved requests ready to be paid or disbursed.',
                    'count' => $approvedRequisitions,
                    'route' => route('requisitions.index', ['status' => 'approved']),
                    'permission' => 'requisitions.index',
                    'icon' => 'mdi-clipboard-check-outline',
                ],
                [
                    'stage' => 'Expenses Recorded',
                    'description' => 'Direct expenses and petty cash disbursements captured this month.',
                    'count' => $expensesCountThisMonth,
                    'route' => route('expenses.index'),
                    'permission' => 'expenses.index',
                    'icon' => 'mdi-cash-minus',
                ],
                [
                    'stage' => 'Invoices Issued',
                    'description' => 'Client invoices raised and sent for payment.',
                    'count' => $issuedInvoices,
                    'route' => route('finance.index'),
                    'permission' => 'finance.index',
                    'icon' => 'mdi-file-document-edit-outline',
                ],
                [
                    'stage' => 'Payments Received',
                    'description' => 'Invoices with part or full payment recorded.',
                    'count' => $paidInvoices,
                    'route' => route('finance.index'),
                    'permission' => 'finance.index',
                    'icon' => 'mdi-cash-check',
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
