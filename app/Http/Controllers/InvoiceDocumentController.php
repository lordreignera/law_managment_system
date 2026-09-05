<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceDocumentController extends Controller
{
    private const TYPES = [
        'invoice' => 'Invoice',
        'fee-note' => 'Fee Note',
    ];

    public function show(Request $request, Invoice $invoice, string $type = 'invoice')
    {
        return view('modules.finance.invoices.document', $this->documentData($invoice, $type));
    }

    public function pdf(Request $request, Invoice $invoice, string $type = 'invoice')
    {
        $data = $this->documentData($invoice, $type);
        $data['logoSource'] = $data['company']->logo_public_path;
        $data['isPdf'] = true;

        return Pdf::loadView('modules.finance.invoices.pdf', $data)
            ->download($this->filename($invoice, $type));
    }

    private function documentData(Invoice $invoice, string $type): array
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $invoice->load(['client', 'matter.practiceArea', 'matter.client']);
        $company = CompanySetting::current();

        return [
            'company' => $company,
            'invoice' => $invoice,
            'documentType' => $type,
            'documentTitle' => self::TYPES[$type],
            'logoSource' => $company->logo_url,
            'isPdf' => false,
        ];
    }

    private function filename(Invoice $invoice, string $type): string
    {
        $prefix = $type === 'fee-note' ? 'fee-note' : 'invoice';

        return $prefix.'-'.Str::of($invoice->invoice_no)->replace(['/', '\\', ' '], '-')->lower().'.pdf';
    }
}
