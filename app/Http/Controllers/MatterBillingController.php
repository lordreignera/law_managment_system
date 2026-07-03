<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Matter;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MatterBillingController extends Controller
{
    public function show(Matter $matter)
    {
        return view('modules.matters.billing', [
            'matter' => $matter->load(['client', 'practiceArea', 'invoices', 'expenses.category', 'expenses.recorder']),
            'expenseCategories' => ExpenseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'paymentSources' => Expense::PAYMENT_SOURCES,
            'invoiceStatuses' => [
                'draft' => 'Draft',
                'sent' => 'Sent',
                'part_paid' => 'Part Paid',
                'paid' => 'Paid',
                'void' => 'Void',
            ],
        ]);
    }

    public function storeInvoice(Request $request, Matter $matter)
    {
        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'sent', 'part_paid', 'paid', 'void'])],
        ]);

        $subtotal = (float) $data['subtotal'];
        $tax = (float) ($data['tax'] ?? 0);
        $paidAmount = (float) ($data['paid_amount'] ?? 0);

        Invoice::create([
            'client_id' => $matter->client_id,
            'matter_id' => $matter->id,
            'invoice_no' => MonthlyReferenceNumber::make(Invoice::class, 'invoice_no', 'INV'),
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'paid_amount' => $paidAmount,
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('matters.billing.show', $matter)
            ->with('status', 'Invoice recorded for '.$matter->reference_no.'.');
    }

    public function storeCost(Request $request, Matter $matter)
    {
        $data = $request->validate([
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_source' => ['required', 'in:'.implode(',', array_keys(Expense::PAYMENT_SOURCES))],
            'spent_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $request, $matter) {
            Expense::create([
                'reference_no' => MonthlyReferenceNumber::make(Expense::class, 'reference_no', 'EX'),
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'matter_id' => $matter->id,
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
        });

        return redirect()
            ->route('matters.billing.show', $matter)
            ->with('status', 'Cost recorded for '.$matter->reference_no.'.');
    }
}
