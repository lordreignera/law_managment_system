<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ChartAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Matter;
use App\Models\PettyCashTransaction;
use App\Models\Requisition;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public function index()
    {
        return view('modules.finance.index', [
            'invoices' => Invoice::with(['client', 'matter'])->latest('invoice_date')->limit(10)->get(),
            'payments' => $this->recentPayments(),
            'openInvoices' => $this->openInvoices(),
            'paymentAccounts' => $this->paymentAccounts(),
            'requisitions' => Requisition::with(['requester', 'category'])->latest()->limit(10)->get(),
        ]);
    }

    public function createInvoice()
    {
        return view('modules.finance.invoices.create', [
            'invoiceNumber' => MonthlyReferenceNumber::make(Invoice::class, 'invoice_no', 'INV'),
            'clients' => Client::orderBy('name')->orderBy('first_name')->get(['id', 'name', 'first_name', 'middle_name', 'last_name', 'organization_name']),
            'matters' => Matter::with('client')->orderByDesc('id')->limit(300)->get(['id', 'client_id', 'reference_no', 'title']),
            'invoiceStatuses' => $this->invoiceStatuses(),
        ]);
    }

    public function storeInvoice(Request $request)
    {
        $request->merge([
            'subtotal' => $this->moneyInput($request->input('subtotal')),
            'tax' => $this->moneyInput($request->input('tax')),
        ]);

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->invoiceStatuses()))],
            'from' => ['nullable', 'string'],
        ]);

        if (! empty($data['matter_id'])) {
            $matter = Matter::findOrFail($data['matter_id']);

            if ((int) $matter->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages(['matter_id' => 'The selected matter must belong to the selected client.']);
            }
        }

        $subtotal = (float) $data['subtotal'];
        $tax = (float) ($data['tax'] ?? 0);

        Invoice::create([
            'client_id' => $data['client_id'],
            'matter_id' => $data['matter_id'] ?? null,
            'invoice_no' => MonthlyReferenceNumber::make(Invoice::class, 'invoice_no', 'INV'),
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'paid_amount' => 0,
            'status' => $data['status'],
        ]);

        return $this->financeRedirect($request, 'Invoice recorded.');
    }

    public function createPayment(Request $request)
    {
        if (! Schema::hasTable('invoice_payments')) {
            return redirect()
                ->route('finance.dashboard')
                ->withErrors(['payments' => 'Run migrations to activate invoice payment records.']);
        }

        if ($request->input('from') === 'finance-dashboard') {
            return redirect()->route('finance.dashboard', [
                'payment_modal' => 1,
                'invoice_id' => $request->integer('invoice_id') ?: null,
            ]);
        }

        return view('modules.finance.payments.create', [
            'invoices' => $this->openInvoices(),
            'paymentAccounts' => $this->paymentAccounts(),
            'selectedInvoice' => $request->integer('invoice_id') ? Invoice::find($request->integer('invoice_id')) : null,
        ]);
    }

    public function storePayment(Request $request)
    {
        if (! Schema::hasTable('invoice_payments')) {
            return back()
                ->withInput()
                ->withErrors(['payments' => 'Run migrations to activate invoice payment records.']);
        }

        $request->merge([
            'amount' => $this->moneyInput($request->input('amount')),
        ]);

        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'chart_account_id' => ['required', 'exists:chart_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'from' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);

            if ((float) $data['amount'] > $invoice->balance) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding invoice balance.']);
            }

            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'chart_account_id' => $data['chart_account_id'],
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $request->user()->id,
            ]);

            $paidAmount = (float) $invoice->paid_amount + (float) $data['amount'];

            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= (float) $invoice->total ? 'paid' : 'part_paid',
            ]);
        });

        return $this->financeRedirect($request, 'Payment recorded.');
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

        $financeSource = ['from' => 'finance-dashboard'];

        return view('modules.finance.dashboard', [
            'stats' => [
                [
                    'label' => 'Total Invoiced',
                    'value' => number_format($invoiceTotal),
                    'icon' => 'mdi-file-document-outline',
                    'route' => route('finance.index', $financeSource),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Collected',
                    'value' => number_format($invoicePaid),
                    'icon' => 'mdi-cash-check',
                    'route' => route('finance.index', $financeSource),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Outstanding',
                    'value' => number_format(max($invoiceTotal - $invoicePaid, 0)),
                    'icon' => 'mdi-receipt-text-clock-outline',
                    'route' => route('finance.index', $financeSource),
                    'permission' => 'finance.index',
                ],
                [
                    'label' => 'Petty Cash Balance',
                    'value' => number_format(PettyCashTransaction::balance()),
                    'icon' => 'mdi-wallet-outline',
                    'route' => route('petty-cash.index', $financeSource),
                    'permission' => 'petty-cash.index',
                ],
                [
                    'label' => 'Expenses This Month',
                    'value' => number_format($expensesThisMonth),
                    'icon' => 'mdi-cash-minus',
                    'route' => route('expenses.index', $financeSource),
                    'permission' => 'expenses.index',
                ],
                [
                    'label' => 'Pending Requisitions',
                    'value' => number_format($submittedRequisitions),
                    'icon' => 'mdi-clipboard-clock-outline',
                    'route' => route('requisitions.index', ['status' => 'submitted'] + $financeSource),
                    'permission' => 'requisitions.index',
                ],
                [
                    'label' => 'Chart Accounts',
                    'value' => number_format(ChartAccount::count()),
                    'icon' => 'mdi-format-list-numbered',
                    'route' => route('finance.chart-accounts.index', $financeSource),
                    'permission' => 'finance.chart-accounts.index',
                ],
            ],
            'flow' => [
                [
                    'stage' => 'Requisitions Submitted',
                    'description' => 'Spending requests awaiting approval before funds are released.',
                    'count' => $submittedRequisitions,
                    'route' => route('requisitions.index', ['status' => 'submitted'] + $financeSource),
                    'permission' => 'requisitions.index',
                    'icon' => 'mdi-clipboard-text-clock-outline',
                ],
                [
                    'stage' => 'Approved Requisitions',
                    'description' => 'Approved requests ready to be paid or disbursed.',
                    'count' => $approvedRequisitions,
                    'route' => route('requisitions.index', ['status' => 'approved'] + $financeSource),
                    'permission' => 'requisitions.index',
                    'icon' => 'mdi-clipboard-check-outline',
                ],
                [
                    'stage' => 'Expenses Recorded',
                    'description' => 'Direct expenses and petty cash disbursements captured this month.',
                    'count' => $expensesCountThisMonth,
                    'route' => route('expenses.index', $financeSource),
                    'permission' => 'expenses.index',
                    'icon' => 'mdi-cash-minus',
                ],
                [
                    'stage' => 'Invoices Issued',
                    'description' => 'Client invoices raised and sent for payment.',
                    'count' => $issuedInvoices,
                    'route' => route('finance.invoices.create', $financeSource),
                    'permission' => 'finance.invoices.create',
                    'icon' => 'mdi-file-document-edit-outline',
                ],
                [
                    'stage' => 'Payments Received',
                    'description' => 'Invoices with part or full payment recorded.',
                    'count' => $paidInvoices,
                    'route' => route('finance.payments.create', $financeSource),
                    'permission' => 'finance.payments.create',
                    'icon' => 'mdi-cash-check',
                    'modal' => 'finance-payment-modal',
                ],
            ],
            'recentInvoices' => Invoice::with('client')->latest('invoice_date')->limit(8)->get(),
            'recentExpenses' => Expense::with(['category', 'recorder'])->latest('spent_on')->limit(8)->get(),
            'pendingRequisitions' => Requisition::with(['requester', 'category'])
                ->where('status', 'submitted')
                ->latest()
                ->limit(8)
                ->get(),
            'openInvoices' => $this->openInvoices(),
            'paymentAccounts' => $this->paymentAccounts(),
            'selectedPaymentInvoice' => request()->integer('invoice_id') ? Invoice::find(request()->integer('invoice_id')) : null,
            'recentPayments' => $this->recentPayments(),
        ]);
    }

    private function openInvoices()
    {
        return Invoice::with(['client', 'matter'])
            ->whereColumn('paid_amount', '<', 'total')
            ->orderByDesc('invoice_date')
            ->limit(300)
            ->get();
    }

    private function paymentAccounts()
    {
        return ChartAccount::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where(function ($query) {
                $query
                    ->where('is_bank_account', true)
                    ->orWhere('is_cash_account', true)
                    ->orWhere('is_client_funds_account', true);
            })
            ->orderBy('account_number')
            ->get(['id', 'account_number', 'name']);
    }

    private function recentPayments()
    {
        if (! Schema::hasTable('invoice_payments')) {
            return collect();
        }

        return InvoicePayment::with(['invoice.client', 'account', 'receiver'])
            ->latest('paid_on')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    private function invoiceStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'part_paid' => 'Part Paid',
            'paid' => 'Paid',
            'void' => 'Void',
        ];
    }

    private function financeRedirect(Request $request, string $status)
    {
        return redirect()
            ->route($request->input('from') === 'finance-dashboard' ? 'finance.dashboard' : 'finance.index')
            ->with('status', $status);
    }

    private function moneyInput(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
